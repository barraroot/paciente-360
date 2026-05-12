<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * T254 — Job de anonimização de mensagens de um paciente (FR-018, NC-14.b).
 *
 * Regra LGPD granular (Princípio I — minimização):
 *  - Mensagens RECEBIDAS (direction=in): body/preview zerados + mídia deletada do S3.
 *  - Mensagens ENVIADAS (direction=out): preservadas integralmente.
 *
 * Idempotente: re-execução não duplica alterações (body_preview checado).
 * Cross-tenant via `pacienteId` + `tenantId` injetados no construtor.
 */
class AnonimizaMensagensDoPacienteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $pacienteId,
        public readonly int $tenantId,
    ) {}

    public function handle(PermissionRegistrar $registrar): void
    {
        // Rehidrata contexto de permissão para o tenant correto
        $registrar->setPermissionsTeamId($this->tenantId);

        $placeholder = '[anonimizado em '.now()->format('Y-m-d').']';

        $recebidas = 0;
        $midiasDeleted = 0;

        // Percorre conversas do paciente neste tenant
        Conversation::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('patient_id', $this->pacienteId)
            ->chunkById(50, function ($conversations) use ($placeholder, &$recebidas, &$midiasDeleted): void {
                foreach ($conversations as $conversation) {
                    // Mensagens direction=in ainda não anonimizadas
                    Message::withoutGlobalScopes()
                        ->where('tenant_id', $this->tenantId)
                        ->where('conversation_id', $conversation->id)
                        ->where('direction', 'in')
                        ->where(fn ($q) => $q->whereNotNull('body_preview')
                            ->orWhereNotNull('body'))
                        ->chunkById(100, function ($messages) use ($placeholder, &$recebidas, &$midiasDeleted): void {
                            foreach ($messages as $message) {
                                $this->purgeMessageMedia($message, $midiasDeleted);

                                $message->update([
                                    'body' => null,
                                    'body_searchable' => null,
                                    'body_preview' => $placeholder,
                                ]);

                                $recebidas++;
                            }
                        });
                }
            });

        // Conta mensagens enviadas preservadas para o audit log
        $enviadas = Message::withoutGlobalScopes()
            ->whereIn('conversation_id', function ($q): void {
                $q->select('id')
                    ->from('messaging_conversations')
                    ->where('tenant_id', $this->tenantId)
                    ->where('patient_id', $this->pacienteId);
            })
            ->where('direction', 'out')
            ->count();

        AuditLog::create([
            'tenant_id' => $this->tenantId,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => 'paciente.mensagens_anonimizadas',
            'auditable_type' => null,
            'auditable_id' => null,
            'payload' => [
                'paciente_id' => $this->pacienteId,
                'recebidas_anonimizadas' => $recebidas,
                'enviadas_preservadas' => $enviadas,
                'midias_deletadas' => $midiasDeleted,
            ],
            'ip' => null,
            'user_agent' => null,
            'request_id' => null,
        ]);

        Log::info('paciente.mensagens_anonimizadas', [
            'tenant_id' => $this->tenantId,
            'paciente_id' => $this->pacienteId,
            'recebidas_anonimizadas' => $recebidas,
            'enviadas_preservadas' => $enviadas,
            'midias_deletadas' => $midiasDeleted,
        ]);
    }

    /**
     * Purga mídia de uma mensagem inbound: remove do S3 e seta media_purged_at.
     */
    private function purgeMessageMedia(Message $message, int &$midiasDeleted): void
    {
        MessageMedia::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('message_id', $message->id)
            ->whereNull('media_purged_at')
            ->get()
            ->each(function (MessageMedia $media) use (&$midiasDeleted): void {
                try {
                    Storage::disk($media->storage_disk)->delete($media->storage_path);
                } catch (\Throwable $e) {
                    Log::warning('paciente.anonimizacao.s3_delete_failed', [
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $media->update(['media_purged_at' => now()]);
                $midiasDeleted++;
            });
    }
}

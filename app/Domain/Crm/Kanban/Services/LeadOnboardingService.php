<?php

declare(strict_types=1);

namespace App\Domain\Crm\Kanban\Services;

use App\Domain\Crm\Kanban\Events\KanbanCardCurated;
use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Support\Metrics\AiMetricsContract;
use App\Support\Telefone\TelefoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **T081 (Fase 18 — US2, FR-009/011/012/013)** — Onboarding automático de
 * lead no kanban a partir de mensagem inbound.
 *
 * Estratégia:
 *  - WhatsApp → identificador = telefone E.164 normalizado (`telefone_primario_normalizado`)
 *  - Instagram Direct → `instagram_handle` (IGSID estável)
 *  - Widget de site → `widget_anonymous_id` (UUID do anônimo)
 *
 * Comportamentos:
 *  - **Contato novo**: cria `Paciente status='lead'` + insere na coluna
 *    `is_initial=true` do funil + emite `KanbanCardCurated(event_kind='lead_created')`.
 *  - **Contato já é lead** (em qualquer coluna não-terminal): no-op idempotente.
 *  - **Contato já é lead** (em coluna terminal — `is_terminal=true`):
 *    reabre para a coluna inicial + emite `lead_reactivated` (FR-013).
 *  - **Contato é paciente regular (não-lead)** (Q-clarify-3=B / FR-011a):
 *    NÃO entra no kanban; emite `inbound_attached_to_existing_patient`
 *    (audit-only, sem mutação no card).
 *
 * Idempotência forte (FR-012): chamadas concorrentes para o mesmo
 * identificador resolvem para o mesmo Paciente (UNIQUE parcial em DB +
 * `firstOrCreate` envolvido em transação).
 */
final class LeadOnboardingService
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_INSTAGRAM = 'instagram';

    public const CHANNEL_WIDGET = 'web';

    public function __construct(
        private readonly KanbanPipelineMappingService $mappings,
        private readonly AiMetricsContract $metrics,
    ) {}

    /**
     * Garante a existência do contato e, se for lead novo/reaberto, posiciona
     * no kanban. Retorna o `Paciente` resolvido OU null em falha auditada.
     */
    public function ensureFor(string $channelType, string $identifier, int $tenantId): ?Paciente
    {
        try {
            return DB::transaction(function () use ($channelType, $identifier, $tenantId): ?Paciente {
                $existing = $this->findExisting($channelType, $identifier, $tenantId);

                if ($existing === null) {
                    return $this->createNewLead($channelType, $identifier, $tenantId);
                }

                // FR-011a (Q-clarify-3=B) — paciente regular NÃO entra no kanban.
                if ($existing->status !== 'lead') {
                    $this->emitCuration(
                        tenantId: $tenantId,
                        pacienteId: $existing->id,
                        eventKind: 'inbound_attached_to_existing_patient',
                        applied: true,
                        suppressionReason: null,
                        reason: 'Mensagem anexada ao prontuário; sem novo card no kanban.',
                    );

                    return $existing;
                }

                // FR-013 — lead em coluna terminal volta para a inicial.
                if ($this->isInTerminalColumn($existing)) {
                    $this->reactivateTerminalLead($existing, $tenantId);
                }

                return $existing;
            });
        } catch (Throwable $e) {
            $this->handleFailure($channelType, $identifier, $tenantId, $e);

            return null;
        }
    }

    private function findExisting(string $channelType, string $identifier, int $tenantId): ?Paciente
    {
        $column = $this->columnForChannel($channelType);

        if ($column === null) {
            return null;
        }

        $normalized = $this->normalize($channelType, $identifier);

        return Paciente::query()
            ->where('tenant_id', $tenantId)
            ->where($column, $normalized)
            ->first();
    }

    private function createNewLead(string $channelType, string $identifier, int $tenantId): ?Paciente
    {
        $coluna = $this->mappings->colunaFor($tenantId, 'lead_created');

        if ($coluna === null) {
            // Tenant sem coluna inicial nem mapping → falha auditada (FR-015).
            $this->handleFailure(
                $channelType,
                $identifier,
                $tenantId,
                new \RuntimeException('No initial kanban column configured for tenant'),
            );

            return null;
        }

        $column = $this->columnForChannel($channelType);
        $normalized = $this->normalize($channelType, $identifier);

        $attrs = [
            'nome' => $this->placeholderName($channelType),
            'status' => 'lead',
            'origem' => $this->origemFor($channelType),
            'origem_origem' => 'canal',
            'funil_coluna_atual_id' => $coluna->id,
        ];

        if ($column === 'telefone_primario_normalizado') {
            // É uma coluna GENERATED — precisamos passar via `telefone_primario`.
            $attrs['telefone_primario'] = $identifier;
        } else {
            $attrs[$column] = $normalized;
        }

        // tenant_id não está em $fillable — setá direto antes de save
        $paciente = Paciente::make($attrs);
        $paciente->tenant_id = $tenantId;
        $paciente->save();

        $this->emitCuration(
            tenantId: $tenantId,
            pacienteId: $paciente->id,
            eventKind: 'lead_created',
            applied: true,
            suppressionReason: null,
            toColunaId: $coluna->id,
            reason: 'Lead criado automaticamente a partir de mensagem inbound do canal '.$channelType,
        );

        return $paciente;
    }

    private function isInTerminalColumn(Paciente $paciente): bool
    {
        if ($paciente->funil_coluna_atual_id === null) {
            return false;
        }

        return (bool) FunilColuna::query()
            ->where('tenant_id', $paciente->tenant_id)
            ->where('id', $paciente->funil_coluna_atual_id)
            ->where('is_terminal', true)
            ->exists();
    }

    private function reactivateTerminalLead(Paciente $paciente, int $tenantId): void
    {
        $initial = $this->mappings->initialColumn($tenantId);

        if ($initial === null) {
            return;
        }

        $fromColunaId = $paciente->funil_coluna_atual_id;
        $paciente->update(['funil_coluna_atual_id' => $initial->id]);

        $this->emitCuration(
            tenantId: $tenantId,
            pacienteId: $paciente->id,
            eventKind: 'lead_reactivated',
            applied: true,
            suppressionReason: null,
            fromColunaId: $fromColunaId,
            toColunaId: $initial->id,
            reason: 'Lead em coluna terminal reabriu por nova mensagem inbound (FR-013).',
        );
    }

    private function emitCuration(
        int $tenantId,
        int $pacienteId,
        string $eventKind,
        bool $applied,
        ?string $suppressionReason,
        ?int $fromColunaId = null,
        ?int $toColunaId = null,
        ?string $reason = null,
    ): void {
        $event = KanbanCurationEvent::create([
            'tenant_id' => $tenantId,
            'paciente_id' => $pacienteId,
            'event_kind' => $eventKind,
            'source' => 'auto_listener',
            'from_coluna_id' => $fromColunaId,
            'to_coluna_id' => $toColunaId,
            'applied' => $applied,
            'suppression_reason' => $suppressionReason,
            'actor_type' => 'system',
            'reason' => $reason,
            'created_at' => now(),
        ]);

        event(new KanbanCardCurated($event));

        $this->metrics->kanbanCurationEvent(
            tenantId: $tenantId,
            source: 'auto_listener',
            eventKind: $eventKind,
        );
    }

    private function handleFailure(string $channelType, string $identifier, int $tenantId, Throwable $e): void
    {
        // FR-015 — falhas (tenant suspenso, sem coluna inicial, FK violation, etc.)
        // são auditadas via log estruturado. NÃO persiste `KanbanCurationEvent`
        // porque a FK `paciente_id` é NOT NULL e ainda não há `Paciente` real.
        // Operador é alertado via Sentry/Pail (Princípio V). Quando o tenant
        // já tem pelo menos 1 paciente, futuras tentativas que sucedam farão
        // a auditoria via KanbanCurationEvent normal.
        Log::warning('lead_onboarding.failed', [
            'tenant_id' => $tenantId,
            'channel' => $channelType,
            'identifier' => mb_substr($identifier, 0, 5).'***',
            'error' => $e->getMessage(),
        ]);
    }

    private function columnForChannel(string $channelType): ?string
    {
        return match ($channelType) {
            self::CHANNEL_WHATSAPP => 'telefone_primario_normalizado',
            self::CHANNEL_INSTAGRAM => 'instagram_handle',
            self::CHANNEL_WIDGET => 'widget_anonymous_id',
            default => null,
        };
    }

    private function normalize(string $channelType, string $identifier): string
    {
        if ($channelType === self::CHANNEL_WHATSAPP) {
            try {
                return preg_replace('/\D/', '', TelefoneNormalizer::normalize($identifier)) ?? $identifier;
            } catch (Throwable) {
                return preg_replace('/\D/', '', $identifier) ?? $identifier;
            }
        }

        return $identifier;
    }

    private function origemFor(string $channelType): string
    {
        return match ($channelType) {
            self::CHANNEL_WHATSAPP => 'whatsapp',
            self::CHANNEL_INSTAGRAM => 'instagram',
            self::CHANNEL_WIDGET => 'outro',
            default => 'outro',
        };
    }

    private function placeholderName(string $channelType): string
    {
        return match ($channelType) {
            self::CHANNEL_WHATSAPP => 'Contato (WhatsApp)',
            self::CHANNEL_INSTAGRAM => 'Contato (Instagram)',
            self::CHANNEL_WIDGET => 'Visitante (site)',
            default => 'Contato',
        };
    }
}

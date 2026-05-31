<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Domain\Messaging\Audio\Inbound\Models\AudioTranscription;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Jobs\Compliance\PurgeExpiredAudioRawJob;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **T213 (Fase 18 — Polish, FR-055a/b)** — quando paciente tem consent
 * `Transcricao` ativo, áudios antigos NÃO são apagados pelo cron (mesmo
 * estando além do prazo padrão).
 */
final class PurgeRespectsConsentTranscricaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_preserved_when_patient_has_transcricao_consent(): void
    {
        Storage::fake('media');
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $paciente = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id, 'type' => 'whatsapp']);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'patient_id' => $paciente->id,
        ]);

        // Concede consent Transcricao ANTES do purge.
        app(ConsentService::class)->record(
            patient: $paciente,
            channel: 'panel',
            finalidade: ConsentFinalidade::Transcricao,
        );

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'body' => 'preservado',
        ]);

        // Mídia antiga (>90d) — message_id é obrigatório (FK).
        $oldMedia = MessageMedia::factory()->create([
            'tenant_id' => $tenant->id,
            'message_id' => $message->id,
            'storage_disk' => 'media',
            'storage_path' => 'tenant_'.$tenant->id.'/preserved-audio.ogg',
            'mime_type' => 'audio/ogg',
            'media_purged_at' => null,
            'created_at' => Carbon::now()->subDays(180),
            'updated_at' => Carbon::now()->subDays(180),
        ]);
        Storage::disk('media')->put($oldMedia->storage_path, 'fake-bytes');

        AudioTranscription::create([
            'tenant_id' => $tenant->id,
            'message_id' => $message->id,
            'media_id' => $oldMedia->id,
            'provider' => 'openai_whisper',
            'transcribed_text' => 'preservado',
            'truncated' => false,
        ]);

        (new PurgeExpiredAudioRawJob)->handle(app(ConsentService::class));

        $oldMedia->refresh();
        $this->assertNotNull(
            $oldMedia->storage_path,
            'Áudio deve ser preservado quando paciente tem consent Transcricao.',
        );
        $this->assertNull($oldMedia->media_purged_at);
        $this->assertTrue(
            Storage::disk('media')->exists('tenant_'.$tenant->id.'/preserved-audio.ogg'),
        );
    }
}

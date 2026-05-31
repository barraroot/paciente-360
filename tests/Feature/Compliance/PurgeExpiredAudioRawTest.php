<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Domain\Messaging\Audio\Inbound\Models\AudioTranscription;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Domain\Privacy\Services\ConsentService;
use App\Events\Compliance\AudioRawPurged;
use App\Jobs\Compliance\PurgeExpiredAudioRawJob;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **T213 (Fase 18 — Polish, FR-055a)** — PurgeExpiredAudioRawJob: sem consent
 * `Transcricao`, áudios além do prazo padrão são apagados.
 */
final class PurgeExpiredAudioRawTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_old_audio_without_consent(): void
    {
        Event::fake([AudioRawPurged::class]);
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

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'body' => 'transcrição em texto preservada',
        ]);

        // Mídia antiga (>90d) sem purge ainda — message_id é obrigatório (FK).
        $oldMedia = MessageMedia::factory()->create([
            'tenant_id' => $tenant->id,
            'message_id' => $message->id,
            'storage_disk' => 'media',
            'storage_path' => 'tenant_'.$tenant->id.'/old-audio.ogg',
            'mime_type' => 'audio/ogg',
            'media_purged_at' => null,
            'created_at' => Carbon::now()->subDays(120),
            'updated_at' => Carbon::now()->subDays(120),
        ]);

        // Garante que o arquivo "existe" no fake disk.
        Storage::disk('media')->put($oldMedia->storage_path, 'fake-bytes');

        $transcription = AudioTranscription::create([
            'tenant_id' => $tenant->id,
            'message_id' => $message->id,
            'media_id' => $oldMedia->id,
            'provider' => 'openai_whisper',
            'transcribed_text' => 'transcrição em texto preservada',
            'truncated' => false,
        ]);

        // Roda o job inline.
        (new PurgeExpiredAudioRawJob)->handle(app(ConsentService::class));

        $oldMedia->refresh();
        $transcription->refresh();

        $this->assertNull($oldMedia->storage_path, 'storage_path deve ser null após purge.');
        $this->assertNotNull($oldMedia->media_purged_at);
        $this->assertFalse(
            Storage::disk('media')->exists('tenant_'.$tenant->id.'/old-audio.ogg'),
            'Arquivo no disk deve ter sido deletado.',
        );
        $this->assertEquals(
            'transcrição em texto preservada',
            $transcription->transcribed_text,
            'Texto deve permanecer — apenas o áudio bruto é apagado.',
        );

        Event::assertDispatched(
            AudioRawPurged::class,
            fn (AudioRawPurged $e): bool => $e->tenantId === $tenant->id
                && $e->reason === 'expired_no_consent'
                && $e->audioCount === 1,
        );
    }

    public function test_does_not_purge_recent_audio_within_window(): void
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

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'body' => 'recente',
        ]);

        // Mídia recente (<90d) — não deve ser apagada.
        $recentMedia = MessageMedia::factory()->create([
            'tenant_id' => $tenant->id,
            'message_id' => $message->id,
            'storage_disk' => 'media',
            'storage_path' => 'tenant_'.$tenant->id.'/recent-audio.ogg',
            'mime_type' => 'audio/ogg',
            'media_purged_at' => null,
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(10),
        ]);
        Storage::disk('media')->put($recentMedia->storage_path, 'fake-bytes');

        AudioTranscription::create([
            'tenant_id' => $tenant->id,
            'message_id' => $message->id,
            'media_id' => $recentMedia->id,
            'provider' => 'openai_whisper',
            'transcribed_text' => 'recente',
            'truncated' => false,
        ]);

        (new PurgeExpiredAudioRawJob)->handle(app(ConsentService::class));

        $recentMedia->refresh();
        $this->assertNotNull($recentMedia->storage_path);
        $this->assertNull($recentMedia->media_purged_at);
        $this->assertTrue(Storage::disk('media')->exists('tenant_'.$tenant->id.'/recent-audio.ogg'));
    }
}

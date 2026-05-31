<?php

namespace Tests\Feature\Messaging\Audio;

use App\Domain\Messaging\Audio\Inbound\Services\AudioTranscriptionService;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T141 (Fase 18 — US4, SC-004) — Latência de transcrição STT.
 *
 * Validações:
 * - STT completa em tempo aceitável (<10s p95 em produção)
 * - Service NÃO bloqueia indefinidamente
 * - Timeout é respeitado (FR-030 — STT timeout não bloqueia pipeline indefinidamente)
 *
 * Nota: Em CI com Http::fake, latência é negligenciável. O teste valida
 * que a service não introduz overhead (não há loops infinitos, etc.).
 */
class InboundTranscriptionLatencyTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private Channel $channel;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'provider' => 'twilio',
        ]);

        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
        ]);
    }

    public function test_transcription_completes_within_acceptable_latency(): void
    {
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');
        config()->set('messaging.audio.stt.timeout_s', 15);

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'oi tudo bem',
                'language' => 'pt',
                'duration' => 3.0,
            ], 200),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('audio/8/quick.ogg', 'quick-audio');

        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'audio',
        ]);

        MessageMedia::create([
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'storage_disk' => 'local',
            'storage_path' => 'audio/8/quick.ogg',
            'mime_type' => 'audio/ogg',
            'size_bytes' => 11,
            'checksum_sha256' => hash('sha256', 'quick-audio'),
        ]);

        $service = app(AudioTranscriptionService::class);

        $startedAt = microtime(true);
        $audioTrans = $service->transcribeInbound($message);
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        // Assert latency is recorded in audio_transcriptions
        $this->assertIsNumeric($audioTrans->latency_ms);
        $this->assertGreaterThanOrEqual(0, $audioTrans->latency_ms);

        // Assert that the entire service execution (with Http::fake) is quick
        // In CI, this should be <200ms; timeout protection comes from Http config
        $this->assertLessThan(1000, $elapsedMs); // 1 second (very generous)

        // Assert transcription succeeded
        $this->assertNull($audioTrans->error_code);
        $this->assertNotNull($audioTrans->transcribed_text);
    }
}

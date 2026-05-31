<?php

namespace Tests\Feature\Messaging\Audio;

use App\Domain\Messaging\Audio\Inbound\Models\AudioTranscription;
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
 * T136 (Fase 18 — US4, FR-024) — Transcrição de áudio inbound via Instagram Direct.
 *
 * Validações:
 * - Áudio inbound de Instagram é transcrito em PT-BR
 * - Comportamento identico ao WhatsApp (FR-024 agnóstico de canal)
 */
class InboundTranscriptionInstagramTest extends TestCase
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
            'type' => 'instagram',
            'status' => 'ativo',
        ]);

        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => 'ig_user_123456',
        ]);
    }

    public function test_transcribe_instagram_audio_successfully(): void
    {
        // Setup
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');
        config()->set('messaging.audio.stt.timeout_s', 15);
        config()->set('messaging.audio.stt.default_language', 'pt');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'oi, eu gostaria de agendar uma consulta',
                'language' => 'pt',
                'duration' => 8.1,
            ], 200),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('audio/2/msg-2.m4a', 'fake-audio-bytes-ig');

        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'audio',
            'status' => 'delivered',
        ]);

        MessageMedia::create([
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'storage_disk' => 'local',
            'storage_path' => 'audio/2/msg-2.m4a',
            'mime_type' => 'audio/mp4',
            'size_bytes' => 22,
            'checksum_sha256' => hash('sha256', 'fake-audio-bytes-ig'),
        ]);

        // Transcribe
        $service = app(AudioTranscriptionService::class);
        $audioTrans = $service->transcribeInbound($message);

        // Assert
        $this->assertDatabaseHas(AudioTranscription::class, [
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'provider' => 'openai_whisper',
            'truncated' => false,
            'error_code' => null,
        ]);

        $message->refresh();
        $this->assertTrue($message->is_audio_origin);
        $this->assertEquals($message->content_type, 'text');
        $this->assertStringContainsString('agendar', $message->body);
    }
}

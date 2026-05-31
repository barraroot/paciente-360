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
 * T135 (Fase 18 — US4, FR-024/025/029) — Transcrição de áudio inbound via WhatsApp.
 *
 * Validações:
 * - Áudio inbound de WhatsApp é transcrito em PT-BR
 * - Texto transcrito (pseudonimizado) atualiza o body da mensagem
 * - `is_audio_origin=true` marca origem auditiva
 * - `transcription_id` vincula a linha de `audio_transcriptions`
 * - Provedor OpenAI Whisper é usado (mockado)
 */
class InboundTranscriptionWhatsAppTest extends TestCase
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
            'status' => 'ativo',
        ]);

        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => '+5511999999999',
        ]);
    }

    public function test_transcribe_whatsapp_audio_successfully(): void
    {
        // Setup OpenAI API Key
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');
        config()->set('messaging.audio.stt.timeout_s', 15);
        config()->set('messaging.audio.stt.default_language', 'pt');
        config()->set('messaging.audio.stt.max_duration_s', 120);

        // Mock OpenAI Whisper response
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'oi tudo bem, como vai',
                'language' => 'pt',
                'duration' => 5.2,
            ], 200),
        ]);

        // Setup storage
        Storage::fake('local');
        Storage::disk('local')->put('audio/1/msg-1.ogg', 'fake-audio-bytes');

        // Create inbound message with audio media
        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'audio',
            'status' => 'delivered',
            'body' => null,
        ]);

        $media = MessageMedia::create([
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'storage_disk' => 'local',
            'storage_path' => 'audio/1/msg-1.ogg',
            'mime_type' => 'audio/ogg',
            'size_bytes' => 16,
            'checksum_sha256' => hash('sha256', 'fake-audio-bytes'),
            'sensitive_hint' => true,
        ]);

        // Transcribe
        $service = app(AudioTranscriptionService::class);
        $audioTrans = $service->transcribeInbound($message);

        // Assert AudioTranscription
        $this->assertDatabaseHas(AudioTranscription::class, [
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'media_id' => $media->id,
            'provider' => 'openai_whisper',
            'language_detected' => 'pt',
            'truncated' => false,
            'error_code' => null,
            'duration_seconds' => 5,
        ]);
        $this->assertNotNull($audioTrans->transcribed_text);

        // Assert Message updated
        $message->refresh();
        $this->assertTrue($message->is_audio_origin);
        $this->assertEquals($message->content_type, 'text');
        $this->assertNotNull($message->transcription_id);
        $this->assertEquals($message->transcription_id, $audioTrans->id);
        $this->assertStringContainsString('oi', $message->body);
    }
}

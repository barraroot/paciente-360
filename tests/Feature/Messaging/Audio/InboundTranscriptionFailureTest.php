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
 * T138 (Fase 18 — US4, FR-027) — Falhas de transcrição são tratadas graciosamente.
 *
 * Validações:
 * - Erro do provedor (5xx) retorna `error_code='provider_error'`
 * - Áudio corrompido (4xx) retorna `error_code='audio_corrupted'`
 * - Silêncio (texto vazio do provedor) retorna `error_code='silence'`
 * - Message NÃO tem seu body alterado em caso de falha (mantém `content_type='audio'`)
 * - `is_audio_origin=true` ainda é marcado para rastreabilidade
 * - Falha é registrada em AudioTranscription com error_code e error_message
 */
class InboundTranscriptionFailureTest extends TestCase
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

    public function test_provider_error_5xx(): void
    {
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(
                ['error' => 'Service unavailable'],
                503
            ),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('audio/3/msg-3.ogg', 'fake-bytes');

        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'audio',
            'body' => null,
        ]);

        MessageMedia::create([
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'storage_disk' => 'local',
            'storage_path' => 'audio/3/msg-3.ogg',
            'mime_type' => 'audio/ogg',
            'size_bytes' => 9,
            'checksum_sha256' => hash('sha256', 'fake-bytes'),
        ]);

        $service = app(AudioTranscriptionService::class);
        $audioTrans = $service->transcribeInbound($message);

        // Assert error recorded
        $this->assertEquals($audioTrans->error_code, 'provider_error');
        $this->assertNotNull($audioTrans->error_message);

        // Assert message NOT updated with text
        $message->refresh();
        $this->assertTrue($message->is_audio_origin);
        $this->assertEquals($message->content_type, 'audio'); // Still audio
        $this->assertNull($message->body); // Not updated
        $this->assertEquals($message->transcription_id, $audioTrans->id);
    }

    public function test_audio_corrupted_4xx(): void
    {
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(
                ['error' => 'Invalid audio format'],
                400
            ),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('audio/4/msg-4.wav', 'bad-audio');

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
            'storage_path' => 'audio/4/msg-4.wav',
            'mime_type' => 'audio/wav',
            'size_bytes' => 9,
            'checksum_sha256' => hash('sha256', 'bad-audio'),
        ]);

        $service = app(AudioTranscriptionService::class);
        $audioTrans = $service->transcribeInbound($message);

        $this->assertEquals($audioTrans->error_code, 'audio_corrupted');
        $message->refresh();
        $this->assertEquals($message->content_type, 'audio');
    }

    public function test_silence_no_speech(): void
    {
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');

        // Provedor retorna texto vazio → detecção de silêncio
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => '',
                'language' => 'pt',
                'duration' => 3.0,
            ], 200),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('audio/5/silence.ogg', 'quiet-bytes');

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
            'storage_path' => 'audio/5/silence.ogg',
            'mime_type' => 'audio/ogg',
            'size_bytes' => 11,
            'checksum_sha256' => hash('sha256', 'quiet-bytes'),
        ]);

        $service = app(AudioTranscriptionService::class);
        $audioTrans = $service->transcribeInbound($message);

        $this->assertEquals($audioTrans->error_code, 'silence');
        $message->refresh();
        $this->assertEquals($message->content_type, 'audio');
    }
}

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
 * T140 (Fase 18 — US4, FR-055b) — Transcrição com PII é pseudonimizada.
 *
 * Validações:
 * - Texto com CPF no corpo é transcrito sem alteração (armazenado bruto em `audio_transcriptions.transcribed_text`)
 * - Body da mensagem (enviado à IA) tem o CPF pseudonimizado via PiiScrubber
 * - IA recebe texto mascarado, evitando processamento desnecessário de PII (FR-029)
 */
class InboundTranscriptionPiiScrubberTest extends TestCase
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

    public function test_pii_in_transcription_is_scrubbed_for_message_body(): void
    {
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');
        config()->set('messaging.audio.stt.timeout_s', 15);

        // Provedor retorna texto com CPF (que seria vindo de um áudio real)
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'olá meu CPF é 123.456.789-00 e gostaria de agendar',
                'language' => 'pt',
                'duration' => 6.0,
            ], 200),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('audio/7/pii.ogg', 'audio-with-pii');

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
            'storage_path' => 'audio/7/pii.ogg',
            'mime_type' => 'audio/ogg',
            'size_bytes' => 13,
            'checksum_sha256' => hash('sha256', 'audio-with-pii'),
        ]);

        $service = app(AudioTranscriptionService::class);
        $audioTrans = $service->transcribeInbound($message);

        // Assert: transcribed_text mantém o CPF original (cópia bruta)
        $this->assertStringContainsString('123.456.789-00', $audioTrans->transcribed_text);

        // Assert: message.body tem o CPF pseudonimizado (mascarado)
        $message->refresh();
        $this->assertStringNotContainsString('123.456.789-00', $message->body);
        // O PiiScrubber deveria ter mascarado, deixando algo como [CPF]
        $this->assertStringContainsString('agendar', $message->body); // Resto do texto presente
    }
}

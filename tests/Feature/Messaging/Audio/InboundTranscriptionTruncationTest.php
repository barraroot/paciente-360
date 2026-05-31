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
 * T139 (Fase 18 — US4, FR-028) — Áudios longos são truncados com marca visível.
 *
 * Validações:
 * - Áudio com duração > limite configurado (`messaging.audio.stt.max_duration_s`)
 * - `audio_transcriptions.truncated=true`
 * - Mensagem tem marca visível "[áudio truncado em Xs]" no body
 * - Conteúdo até o limite é transcrito normalmente
 */
class InboundTranscriptionTruncationTest extends TestCase
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

    public function test_audio_exceeding_duration_limit_is_truncated(): void
    {
        // Config: max 30 seconds
        config()->set('messaging.audio.stt.openai_api_key', 'sk-test');
        config()->set('messaging.audio.stt.max_duration_s', 30);
        config()->set('messaging.audio.stt.timeout_s', 15);

        // Provedor retorna áudio de 60 segundos
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'olá estou mandando um áudio bem longo explicando tudo que precisa',
                'language' => 'pt',
                'duration' => 60.0,
            ], 200),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('audio/6/long.ogg', str_repeat('x', 1000));

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
            'storage_path' => 'audio/6/long.ogg',
            'mime_type' => 'audio/ogg',
            'size_bytes' => 1000,
            'checksum_sha256' => hash('sha256', str_repeat('x', 1000)),
        ]);

        $service = app(AudioTranscriptionService::class);
        $audioTrans = $service->transcribeInbound($message);

        // Assert truncation flag
        $this->assertTrue($audioTrans->truncated);
        $this->assertEquals($audioTrans->duration_seconds, 60);

        // Assert message body has truncation marker
        $message->refresh();
        $this->assertTrue($message->is_audio_origin);
        $this->assertEquals($message->content_type, 'text');
        $this->assertStringContainsString('[áudio truncado em 30s]', $message->body);
        $this->assertStringContainsString('olá estou mandando', $message->body);
    }
}

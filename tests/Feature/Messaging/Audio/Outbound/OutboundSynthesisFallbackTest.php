<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Audio\Outbound;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Domain\Messaging\Audio\Outbound\Models\AudioSynthesis;
use App\Domain\Messaging\Audio\Outbound\Services\AudioSynthesisService;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T167 (Fase 18 — US5, FR-034) — Síntese TTS com falha do provider.
 *
 * Testa fallback to text:
 * - Provider retorna erro (500)
 * - AudioSynthesis criado com fallback_to_text=true
 * - error_code populado
 * - Nenhum MessageMedia criado
 * - Caller deve enviar como texto
 */
final class OutboundSynthesisFallbackTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private AiPersona $persona;

    private VoiceCatalogEntry $voice;

    private Channel $channel;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake([
            'https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                'Internal Server Error',
                500
            ),
        ]);

        config()->set('messaging.audio.tts.elevenlabs_api_key', 'test-key');
        config()->set('filesystems.default', 'local');

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->voice = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-abc123',
            'display_name' => 'Camila',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $this->persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => $this->voice->id,
        ]);

        $this->channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'status' => 'ativo',
        ]);

        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'external_thread_id' => '+5511999999999',
        ]);
    }

    public function test_synthesis_failure_fallback_to_text(): void
    {
        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
            'content_type' => 'text',
            'body' => 'Test message',
            'status' => 'sent',
        ]);

        $sourceText = 'Mensagem de teste';
        $service = app(AudioSynthesisService::class);
        $synthesis = $service->synthesizeForMessage($message, $this->persona, $sourceText);

        // Assertions — síntese falhou
        $this->assertTrue($synthesis->fallback_to_text);
        $this->assertNotNull($synthesis->error_code);
        $this->assertEquals('provider_error', $synthesis->error_code);
        $this->assertEquals($this->voice->id, $synthesis->voice_id);
        $this->assertNull($synthesis->media_id);

        // Assertions — banco de dados
        $this->assertDatabaseHas(AudioSynthesis::class, [
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'voice_id' => $this->voice->id,
            'fallback_to_text' => true,
            'error_code' => 'provider_error',
        ]);

        // Assertions — nenhum MessageMedia criado
        $this->assertDatabaseMissing(MessageMedia::class, [
            'message_id' => $message->id,
        ]);
    }

    public function test_synthesis_timeout_error(): void
    {
        Http::fake([
            'https://api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                'Gateway Timeout',
                504
            ),
        ]);

        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
            'content_type' => 'text',
            'body' => 'Test',
            'status' => 'sent',
        ]);

        $service = app(AudioSynthesisService::class);
        $synthesis = $service->synthesizeForMessage($message, $this->persona, 'Test message');

        $this->assertTrue($synthesis->fallback_to_text);
        $this->assertEquals('provider_error', $synthesis->error_code);
    }
}

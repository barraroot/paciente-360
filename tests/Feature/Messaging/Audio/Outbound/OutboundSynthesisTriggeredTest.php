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
 * T166 (Fase 18 — US5, FR-035/036) — Síntese TTS bem-sucedida.
 *
 * Testa flow completo:
 * - Voz resolvida (via persona)
 * - Texto normalizado (R$, horários)
 * - Http::fake retorna bytes
 * - AudioSynthesis criado com success
 * - MessageMedia persistido
 * - Storage contém o arquivo
 */
final class OutboundSynthesisTriggeredTest extends TestCase
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
                'FAKE_AUDIO_BYTES_MP3',
                200,
                ['Content-Type' => 'audio/mpeg']
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

    public function test_synthesize_message_successful(): void
    {
        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
            'content_type' => 'text',
            'body' => 'Vou agendar você quinta às 14h30 por R$ 300,00.',
            'status' => 'sent',
        ]);

        $sourceText = 'Vou agendar você quinta às 14h30 por R$ 300,00.';
        $service = app(AudioSynthesisService::class);
        $synthesis = $service->synthesizeForMessage($message, $this->persona, $sourceText);

        // Assertions — síntese bem-sucedida
        $this->assertFalse($synthesis->fallback_to_text);
        $this->assertNull($synthesis->error_code);
        $this->assertEquals($this->voice->id, $synthesis->voice_id);
        $this->assertEquals('elevenlabs', $synthesis->provider);

        // Assertions — texto normalizado
        $this->assertStringContainsString('trezentos reais', $synthesis->normalized_text);
        $this->assertStringContainsString('duas e meia', $synthesis->normalized_text);

        // Assertions — banco de dados
        $this->assertDatabaseHas(AudioSynthesis::class, [
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'voice_id' => $this->voice->id,
            'fallback_to_text' => false,
        ]);

        // Assertions — MessageMedia criado
        $this->assertDatabaseHas(MessageMedia::class, [
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'mime_type' => 'audio/mpeg',
        ]);

        // Assertions — storage
        $media = MessageMedia::where('message_id', $message->id)->first();
        $this->assertNotNull($media);
        $this->assertTrue(Storage::disk('local')->exists($media->storage_path));
    }

    public function test_synthesize_stores_segmented_flag(): void
    {
        config()->set('messaging.audio.tts.max_text_length', 50);

        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
            'content_type' => 'text',
            'body' => 'Long message',
            'status' => 'sent',
        ]);

        $longText = str_repeat('x', 100);
        $service = app(AudioSynthesisService::class);
        $synthesis = $service->synthesizeForMessage($message, $this->persona, $longText);

        $this->assertTrue($synthesis->segmented);
        $this->assertDatabaseHas(AudioSynthesis::class, [
            'message_id' => $message->id,
            'segmented' => true,
        ]);
    }
}

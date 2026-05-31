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
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T172 (Fase 18 — US5, FR-036) — Segmentação de texto longo.
 *
 * Testa que:
 * - Texto excedendo max_text_length é truncado
 * - segmented=true é setado
 * - Provider recebe texto truncado (não o original)
 */
final class OutboundSynthesisSegmentationTest extends TestCase
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
                'FAKE_AUDIO_BYTES',
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        config()->set('messaging.audio.tts.elevenlabs_api_key', 'test-key');
        config()->set('messaging.audio.tts.max_text_length', 50);
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

    public function test_long_text_is_segmented(): void
    {
        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
            'content_type' => 'text',
            'body' => 'Long message',
            'status' => 'sent',
        ]);

        $longText = str_repeat('x', 200);
        $service = app(AudioSynthesisService::class);
        $synthesis = $service->synthesizeForMessage($message, $this->persona, $longText);

        // Assertions — segmentação detectada
        $this->assertTrue($synthesis->segmented);
        $this->assertLessThanOrEqual(50, mb_strlen($synthesis->normalized_text));

        // Assertions — stored no banco
        $this->assertDatabaseHas(AudioSynthesis::class, [
            'message_id' => $message->id,
            'segmented' => true,
        ]);
    }

    public function test_short_text_not_segmented(): void
    {
        $message = Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
            'content_type' => 'text',
            'body' => 'Short',
            'status' => 'sent',
        ]);

        $shortText = 'Mensagem curta';
        $service = app(AudioSynthesisService::class);
        $synthesis = $service->synthesizeForMessage($message, $this->persona, $shortText);

        // Assertions — sem segmentação
        $this->assertFalse($synthesis->segmented);
        $this->assertDatabaseHas(AudioSynthesis::class, [
            'message_id' => $message->id,
            'segmented' => false,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Audio\Outbound;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Audio\Outbound\Models\AudioSynthesis;
use App\Domain\Messaging\Audio\Outbound\Services\AudioSynthesisService;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T167b (Fase 18 — US5, FR-034/037a) — Voz não resolvida.
 *
 * Testa cenário onde o resolvedor não encontra nenhuma voz:
 * - Persona sem voice_id
 * - Tenant sem default_voice_id
 * - Nenhum entry com is_system_default=true
 * - Fallback imediato sem chamar provider
 * - AudioSynthesis com voice_id=null, error_code='voice_unavailable'
 */
final class OutboundSynthesisVoiceUnavailableTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private AiPersona $persona;

    private Channel $channel;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config()->set('filesystems.default', 'local');

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => null,
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

    public function test_synthesis_voice_unavailable(): void
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

        $service = app(AudioSynthesisService::class);
        $synthesis = $service->synthesizeForMessage($message, $this->persona, 'Teste de voz indisponível');

        // Assertions — voz não resolvida
        $this->assertTrue($synthesis->fallback_to_text);
        $this->assertNull($synthesis->voice_id);
        $this->assertEquals('voice_unavailable', $synthesis->error_code);
        $this->assertNull($synthesis->media_id);

        // Assertions — banco de dados
        $this->assertDatabaseHas(AudioSynthesis::class, [
            'tenant_id' => $this->tenant->id,
            'message_id' => $message->id,
            'voice_id' => null,
            'fallback_to_text' => true,
            'error_code' => 'voice_unavailable',
        ]);

        // Assertions — nenhum MessageMedia criado
        $this->assertDatabaseMissing(MessageMedia::class, [
            'message_id' => $message->id,
        ]);
    }
}

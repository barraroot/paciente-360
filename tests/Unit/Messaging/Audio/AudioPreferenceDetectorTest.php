<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Audio;

use App\Domain\Messaging\Audio\Inbound\Services\AudioPreferenceDetector;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T164 (Fase 18 — US5, FR-031/033) — Detecção de preferência por áudio.
 *
 * Testa o detector que olha as últimas N mensagens inbound e busca
 * substrings de gatilhos (normalized). Sem persistência — reavalia a cada turn.
 */
final class AudioPreferenceDetectorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    private Conversation $conversation;

    private AudioPreferenceDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('messaging.audio.outbound.triggers', [
            'nao sei ler', 'nao consigo ler',
            'manda audio', 'me responda em audio',
            'to dirigindo', 'estou dirigindo',
            'so posso ouvir',
        ]);

        $this->detector = app(AudioPreferenceDetector::class);
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

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

    public function test_no_preference_with_regular_message(): void
    {
        Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'text',
            'body' => 'Boa tarde, queria saber o preço',
            'status' => 'delivered',
        ]);

        $result = $this->detector->prefersAudioForConversation($this->conversation);

        $this->assertFalse($result);
    }

    public function test_prefers_audio_with_trigger(): void
    {
        Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'text',
            'body' => 'Não sei ler, manda áudio por favor',
            'status' => 'delivered',
        ]);

        $result = $this->detector->prefersAudioForConversation($this->conversation);

        $this->assertTrue($result);
    }

    public function test_prefers_audio_driving_trigger(): void
    {
        Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'text',
            'body' => 'Tô dirigindo, responde por áudio',
            'status' => 'delivered',
        ]);

        $result = $this->detector->prefersAudioForConversation($this->conversation);

        $this->assertTrue($result);
    }

    public function test_no_preference_without_messages(): void
    {
        $result = $this->detector->prefersAudioForConversation($this->conversation);

        $this->assertFalse($result);
    }

    public function test_lookback_window_5_messages(): void
    {
        // Cria 6 mensagens: a mais velha tem trigger, 5 mais recentes NÃO.
        // Com LOOKBACK=5, a 6ª não é considerada — resultado é false.
        Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'text',
            'body' => 'Não sei ler', // triggar no 1º (mais antigo)
            'status' => 'delivered',
        ]);

        // Cria 5 mais recentes SEM trigger
        for ($i = 0; $i < 5; $i++) {
            Message::create([
                'tenant_id' => $this->tenant->id,
                'conversation_id' => $this->conversation->id,
                'direction' => 'in',
                'sender_type' => 'patient',
                'content_type' => 'text',
                'body' => 'Mensagem normal '.$i,
                'status' => 'delivered',
            ]);
        }

        $result = $this->detector->prefersAudioForConversation($this->conversation);

        // Trigger foi descartado por LOOKBACK=5
        $this->assertFalse($result);
    }

    public function test_prefers_audio_with_recent_trigger(): void
    {
        // Cria 4 mensagens normais, depois 1 com trigger
        for ($i = 0; $i < 4; $i++) {
            Message::create([
                'tenant_id' => $this->tenant->id,
                'conversation_id' => $this->conversation->id,
                'direction' => 'in',
                'sender_type' => 'patient',
                'content_type' => 'text',
                'body' => 'Mensagem normal '.$i,
                'status' => 'delivered',
            ]);
        }

        Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'text',
            'body' => 'Manda áudio please', // trigger na 5ª
            'status' => 'delivered',
        ]);

        $result = $this->detector->prefersAudioForConversation($this->conversation);

        $this->assertTrue($result);
    }

    public function test_empty_triggers_config(): void
    {
        config()->set('messaging.audio.outbound.triggers', []);

        Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'text',
            'body' => 'Não sei ler, manda áudio',
            'status' => 'delivered',
        ]);

        $result = $this->detector->prefersAudioForConversation($this->conversation);

        $this->assertFalse($result);
    }

    public function test_normalize_handles_accents(): void
    {
        // Trigger é "nao sei ler" (sem acento); o envio é "Não Sei Ler" (com acento)
        Message::create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'content_type' => 'text',
            'body' => 'Não consigo ler, me responde em audio por favor',
            'status' => 'delivered',
        ]);

        $result = $this->detector->prefersAudioForConversation($this->conversation);

        $this->assertTrue($result);
    }
}

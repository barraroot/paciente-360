<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Infrastructure\Webhook\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * T038 — Factory para `WebhookEvent`.
 *
 * Sem BelongsToTenant — `tenant_id` e `channel_id` são nullable até resolução
 * no job. Use `->state(['tenant_id' => ..., 'channel_id' => ...])` explicitamente.
 *
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['twilio', 'meta', 'widget']),
            'external_id' => 'SM'.Str::random(32),
            'channel_id' => null,
            'tenant_id' => null,
            'event_type' => 'message_received',
            'raw_payload_encrypted' => json_encode(['mock' => true, 'body' => fake()->sentence()]),
            'signature_verified' => true,
            'received_at' => now(),
            'status' => 'received',
            'processing_started_at' => null,
            'processed_at' => null,
            'attempts' => 0,
            'failure_reason' => null,
        ];
    }

    /**
     * Evento do Twilio WhatsApp.
     */
    public function twilio(): static
    {
        return $this->state([
            'provider' => 'twilio',
            'external_id' => 'SM'.Str::random(32),
            'event_type' => 'message_received',
        ]);
    }

    /**
     * Evento do Meta/Instagram.
     */
    public function meta(): static
    {
        return $this->state([
            'provider' => 'meta',
            'external_id' => (string) fake()->numerify('###############'),
            'event_type' => 'message_received',
        ]);
    }

    /**
     * Evento do Widget web.
     */
    public function widget(): static
    {
        return $this->state([
            'provider' => 'widget',
            'external_id' => Str::uuid()->toString(),
            'event_type' => 'message_received',
        ]);
    }

    /**
     * Evento já processado com sucesso.
     */
    public function processed(): static
    {
        return $this->state([
            'status' => 'processed',
            'processing_started_at' => now()->subSeconds(2),
            'processed_at' => now(),
            'attempts' => 1,
        ]);
    }

    /**
     * Evento com falha.
     */
    public function failed(string $reason = 'Processing error'): static
    {
        return $this->state([
            'status' => 'failed',
            'failure_reason' => $reason,
            'attempts' => 3,
        ]);
    }

    /**
     * Evento duplicado (deduplicado na inserção).
     */
    public function duplicate(): static
    {
        return $this->state(['status' => 'duplicate']);
    }

    /**
     * Assinatura inválida (potencial ataque).
     */
    public function invalidSignature(): static
    {
        return $this->state(['signature_verified' => false]);
    }
}

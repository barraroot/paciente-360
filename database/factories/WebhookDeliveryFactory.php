<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event_type' => 'agendamento.criado',
            'event_id' => fake()->uuid(),
            'correlation_id' => (string) Str::uuid(),
            'payload' => ['foo' => 'bar'],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 5,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'last_response' => null,
            'last_error' => null,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDelivery::STATUS_DELIVERED,
            'attempts' => 1,
            'delivered_at' => now(),
            'last_response' => ['http_code' => 200, 'body_snippet' => 'OK', 'duration_ms' => 120],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => WebhookDelivery::STATUS_FAILED,
            'attempts' => 5,
            'last_error' => 'HTTP 500',
        ]);
    }
}

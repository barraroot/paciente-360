<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Integrations\Models\WebhookDeadLetter;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDeadLetter>
 */
class WebhookDeadLetterFactory extends Factory
{
    protected $model = WebhookDeadLetter::class;

    public function definition(): array
    {
        $failedAt = now();

        return [
            'tenant_id' => Tenant::factory(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'original_delivery_id' => WebhookDelivery::factory(),
            'event_type' => 'agendamento.criado',
            'event_id' => fake()->uuid(),
            'correlation_id' => (string) Str::uuid(),
            'payload' => ['foo' => 'bar'],
            'failure_history' => [
                ['attempt' => 1, 'http_code' => 500, 'error' => 'Server Error', 'occurred_at' => $failedAt->toIso8601String()],
            ],
            'failed_at' => $failedAt,
            'expires_at' => $failedAt->copy()->addDays(30),
            'resent_by_user_id' => null,
            'resent_at' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' Webhook',
            'url' => 'https://'.fake()->domainName().'/webhook',
            'secret' => 'whsec_'.Str::random(48),
            'events_subscribed' => ['agendamento.criado', 'paciente.criado'],
            'is_active' => true,
            'failure_count' => 0,
            'last_success_at' => null,
            'last_failure_at' => null,
            'created_by_user_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function subscribedTo(array $events): static
    {
        return $this->state(fn () => ['events_subscribed' => $events]);
    }
}

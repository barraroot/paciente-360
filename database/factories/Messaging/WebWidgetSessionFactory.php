<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * T038 — Factory para `WebWidgetSession`.
 *
 * @extends Factory<WebWidgetSession>
 */
class WebWidgetSessionFactory extends Factory
{
    protected $model = WebWidgetSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(1, 1440));

        return [
            'visitor_token' => Str::random(64),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
            'referer_domain' => fake()->domainName(),
            'started_at' => $startedAt,
            'last_activity_at' => $startedAt->addMinutes(fake()->numberBetween(0, 60)),
            'identified_patient_id' => null,
            'provisional_data' => [],
            'expires_at' => $startedAt->addDays(30),
        ];
    }

    /**
     * Sessão ativa (não expirada).
     */
    public function active(): static
    {
        return $this->state([
            'started_at' => now()->subHours(1),
            'last_activity_at' => now()->subMinutes(5),
            'expires_at' => now()->addDays(29),
        ]);
    }

    /**
     * Sessão expirada.
     */
    public function expired(): static
    {
        return $this->state([
            'started_at' => now()->subDays(35),
            'last_activity_at' => now()->subDays(35),
            'expires_at' => now()->subDays(5),
        ]);
    }

    /**
     * Sessão com dados provisórios do formulário pré-chat.
     */
    public function comDadosProvisórios(): static
    {
        return $this->state([
            'provisional_data' => [
                'nome' => fake('pt_BR')->name(),
                'telefone' => fake('pt_BR')->numerify('(##) 9####-####'),
            ],
        ]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SuperAdmin\Models\ImpersonateSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T091 (Fase 8 — Lote B US-12.1)** — Factory para {@see ImpersonateSession}.
 *
 * @extends Factory<ImpersonateSession>
 */
class ImpersonateSessionFactory extends Factory
{
    protected $model = ImpersonateSession::class;

    public function definition(): array
    {
        return [
            'super_admin_id' => User::factory()->state(['tenant_id' => null]),
            'tenant_id' => Tenant::factory(),
            'started_at' => Carbon::now()->subMinutes(fake()->numberBetween(1, 60)),
            'ended_at' => null,
            'duration_seconds' => null,
            'scope' => 'full',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'screens_visited_count' => 0,
            'reason' => 'Ticket #'.fake()->numberBetween(1000, 9999).' — investigação de incidente.',
        ];
    }

    public function active(): self
    {
        return $this->state(fn (): array => [
            'ended_at' => null,
            'duration_seconds' => null,
        ]);
    }

    public function ended(?int $durationSeconds = null): self
    {
        return $this->state(function (array $attrs) use ($durationSeconds): array {
            $duration = $durationSeconds ?? fake()->numberBetween(60, 3600);

            return [
                'ended_at' => Carbon::now(),
                'duration_seconds' => $duration,
            ];
        });
    }
}

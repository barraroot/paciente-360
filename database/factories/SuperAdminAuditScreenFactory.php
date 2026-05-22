<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SuperAdmin\Models\ImpersonateSession;
use App\Domain\SuperAdmin\Models\SuperAdminAuditScreen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T091 (Fase 8 — Lote B US-12.1)** — Factory para {@see SuperAdminAuditScreen}.
 *
 * @extends Factory<SuperAdminAuditScreen>
 */
class SuperAdminAuditScreenFactory extends Factory
{
    protected $model = SuperAdminAuditScreen::class;

    public function definition(): array
    {
        return [
            'impersonate_session_id' => ImpersonateSession::factory(),
            'route' => fake()->randomElement([
                'tenant.patients.index',
                'tenant.patients.show',
                'tenant.appointments.index',
                'tenant.prescriptions.show',
                'tenant.settings.show',
            ]),
            'path' => '/api/v1/patients/'.fake()->numberBetween(1, 999),
            'method' => fake()->randomElement(['GET', 'POST', 'PATCH']),
            'visited_at' => Carbon::now()->subMinutes(fake()->numberBetween(0, 30)),
            'ip_address' => fake()->ipv4(),
            'query_params' => null,
        ];
    }
}

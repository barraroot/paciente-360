<?php

namespace Database\Seeders;

use App\Models\Agenda\AppointmentType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * T056 — Seeder com 3 tipos default por tenant ativo (US-6.2 / quickstart §6).
 *
 * Idempotente — usa firstOrCreate em (tenant_id, slug).
 */
class AppointmentTypeSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const DEFAULTS = [
        [
            'nome' => 'Consulta',
            'slug' => 'consulta',
            'duration_minutes' => 30,
            'buffer_minutes' => 5,
            'valor_particular' => 200.00,
            'cor' => '#3B82F6',
        ],
        [
            'nome' => 'Retorno',
            'slug' => 'retorno',
            'duration_minutes' => 15,
            'buffer_minutes' => 0,
            'valor_particular' => 100.00,
            'cor' => '#10B981',
        ],
        [
            'nome' => 'Exame',
            'slug' => 'exame',
            'duration_minutes' => 60,
            'buffer_minutes' => 10,
            'valor_particular' => 300.00,
            'cor' => '#F59E0B',
        ],
    ];

    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            // Definir tenant ativo no container para BelongsToTenant scope auto-popular tenant_id
            app()->instance('tenant', $tenant);

            foreach (self::DEFAULTS as $defaults) {
                AppointmentType::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $defaults['slug']],
                    $defaults + ['is_active' => true]
                );
            }
        });

        app()->forgetInstance('tenant');
    }
}

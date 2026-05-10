<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory para o Model `Tenant` (raiz do multi-tenancy — Princípio II).
 *
 * Gera um tenant válido contra todos os CHECK / UNIQUE constraints da
 * migration `2026_05_10_000001_create_tenants_table.php`:
 *   - `slug` 3..63 chars `[a-z0-9-]` (RFC 1035);
 *   - `cnpj` 14 dígitos numéricos, único;
 *   - `status` em `{trial, active, overdue, suspended, cancelled}`;
 *   - `terms_accepted_at` + `terms_version` obrigatórios.
 *
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = 'clinica-'.Str::lower(Str::random(8));

        return [
            'slug' => $slug,
            'name' => fake()->company(),
            'cnpj' => $this->generateCnpj(),
            'responsible_name' => fake()->name(),
            'responsible_email' => fake()->unique()->safeEmail(),
            'responsible_phone' => '+55'.fake()->numerify('###########'),
            'status' => 'active',
            'trial_ends_at' => null,
            'overdue_since' => null,
            'restrictions_applied_at' => null,
            'plan_id' => null,
            'stripe_customer_id' => null,
            'subdomain_custom' => null,
            'terms_accepted_at' => now(),
            'terms_version' => '1.0',
            'onboarding_state' => '{}',
        ];
    }

    /**
     * Estado: tenant em período de trial.
     */
    public function trial(): static
    {
        return $this->state(fn (): array => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    /**
     * Estado: tenant suspenso (acesso bloqueado).
     */
    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Gera 14 dígitos numéricos para o campo `cnpj`. Não validamos
     * algoritmo de DV aqui — a regra de negócio fica em FormRequest.
     */
    private function generateCnpj(): string
    {
        return (string) fake()->unique()->numerify('##############');
    }
}

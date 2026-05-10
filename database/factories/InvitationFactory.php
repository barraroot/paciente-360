<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory para `Invitation` — convite de usuário interno (US-2.2).
 *
 * Por padrão gera uma invitation `pending` com token_hash fictício.
 * Use o estado `accepted()` ou `revoked()` para cenários de teste.
 *
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'intended_role' => fake()->randomElement(['medico', 'atendente', 'recepcionista', 'financeiro']),
            'inviter_user_id' => User::factory(),
            'token_hash' => Hash::make(Str::random(64)),
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Convite já aceito.
     */
    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => 'accepted',
            'accepted_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Convite revogado pelo admin.
     */
    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => 'revoked',
            'revoked_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * Convite expirado (expires_at no passado).
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => 'pending',
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Associa ao tenant informado (e ao inviter informado).
     */
    public function forTenant(Tenant $tenant, User $inviter): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
            'inviter_user_id' => $inviter->id,
        ]);
    }
}

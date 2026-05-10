<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory do Model `User` (identidade unificada multi-tenant).
 *
 * Por padrão, **não** cria tenant — `tenant_id` fica NULL (Super Admin).
 * Use `forTenant($tenant)` para popular `tenant_id` explicitamente:
 *
 *     User::factory()->forTenant($tenant)->create();
 *
 * Schema em `specs/001-fundacao-multitenant/data-model.md` § 4.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'first_login_at' => null,
            'last_login_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Associa o usuário a um tenant específico (caminho normal — usuário
     * de clínica, não Super Admin).
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}

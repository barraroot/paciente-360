<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T064** — Cria o usuário Super Admin inicial (`tenant_id = NULL`).
 *
 * Em produção, `SUPER_ADMIN_EMAIL` e `SUPER_ADMIN_PASSWORD` são
 * **obrigatórias** (Princípio VII — Segurança Operacional). Em
 * dev/test, fallback `super@admin.local` / `change-me-now-1234`.
 *
 * Idempotente via `User::firstOrCreate(['email' => ...])`.
 *
 * Depende: `RolesSeeder` ter rodado antes (role `super-admin` existe
 * com `tenant_id NULL`).
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $isProduction = app()->environment('production');

        $email = $this->resolveEnv('SUPER_ADMIN_EMAIL', $isProduction, 'admin@flowsys.com.br');
        $password = $this->resolveEnv('SUPER_ADMIN_PASSWORD', $isProduction, 'password');

        // Garante team id "global" (NULL) durante a criação do usuário —
        // Spatie usa o team id corrente ao gravar a pivot row em
        // `model_has_roles`. Super Admin tem `tenant_id NULL`, então o
        // pivot também precisa ser NULL.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'tenant_id' => null,
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }

    /**
     * Resolve env. Em produção, falta de env é fatal.
     */
    private function resolveEnv(string $envKey, bool $isProduction, string $fallback): string
    {
        $value = env($envKey);

        if (! is_string($value) || $value === '') {
            if ($isProduction) {
                throw new RuntimeException(
                    "Env [{$envKey}] é obrigatória em produção para `SuperAdminSeeder`."
                );
            }

            return $fallback;
        }

        return $value;
    }
}

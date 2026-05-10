<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * **T062–T064** — Orquestrador dos seeders default da Fase 0.
 *
 * Ordem importa:
 *   1. RolesSeeder      — roles + permissions template (`tenant_id NULL`).
 *   2. PlansSeeder      — planos comerciais (`starter`, `pro`, `enterprise`).
 *   3. SuperAdminSeeder — usuário Super Admin (depende da role).
 *
 * Todos os seeders são idempotentes — `migrate:fresh --seed` rodado 2×
 * não duplica dados.
 *
 * Para popular dados de desenvolvimento (2 tenants exemplo, com usuários),
 * rode `DevSeeder` separadamente — não é chamado aqui para manter
 * `db:seed` seguro em qualquer ambiente.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PlansSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}

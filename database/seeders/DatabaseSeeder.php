<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * **T062–T064 + T021 (Fase 5)** — Orquestrador dos seeders default.
 *
 * Ordem importa:
 *   1. RolesSeeder              — roles + permissions template (`tenant_id NULL`) — Fase 0/2/3.
 *   2. AgendaPermissionsSeeder  — 11 abilities Fase 5 (Agenda) + sync para tenants existentes.
 *   3. PlansSeeder              — planos comerciais (`starter`, `pro`, `enterprise`).
 *   4. SuperAdminSeeder         — usuário Super Admin (depende da role).
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
            AgendaPermissionsSeeder::class,
            PlansSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}

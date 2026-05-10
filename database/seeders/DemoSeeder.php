<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * **T066** — Placeholder para futuro seeder de demonstração rica
 * (pacientes, agendamentos, conversas IA etc.). Implementação real
 * entra em fases posteriores; nesta fase apenas existe para que
 * `db:seed --class=DemoSeeder` rode sem erro.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (isset($this->command)) {
            $this->command->info('DemoSeeder: rico em fases futuras (TODO).');
        }
    }
}

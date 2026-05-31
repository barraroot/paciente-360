<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **T125-aux (Fase 18 — US3)** — alarga `suppression_reason` para acomodar
 * razões compostas (ex.: `manual_override:from_coluna_X:to_coluna_Y`).
 *
 * O valor original `varchar(60)` (T013) era apertado demais para o caso
 * `mapping_not_configured` + variantes do FR-020 que combinam contexto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE kanban_curation_events ALTER COLUMN suppression_reason TYPE VARCHAR(120)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kanban_curation_events ALTER COLUMN suppression_reason TYPE VARCHAR(60)');
    }
};

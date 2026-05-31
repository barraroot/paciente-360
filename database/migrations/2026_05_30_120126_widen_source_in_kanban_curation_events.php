<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **T125-aux2 (Fase 18 — US3)** — alarga `source` para acomodar
 * `manual_override_blocked` (22 chars). Original `varchar(20)` da T013
 * era apertado demais.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE kanban_curation_events ALTER COLUMN source TYPE VARCHAR(30)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kanban_curation_events ALTER COLUMN source TYPE VARCHAR(20)');
    }
};

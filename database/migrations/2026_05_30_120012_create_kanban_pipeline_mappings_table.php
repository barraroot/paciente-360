<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T012 (Fase 18 — US3)** — mapping evento→coluna do funil, por tenant.
 *
 * Cada tenant tem seu pipeline; o mapping default é seedado por
 * DefaultKanbanPipelineMappingSeeder (T035) idempotente.
 *
 * `event_kind` é o gatilho de domínio (lead_created, qualification_started,
 * value_accepted, slot_held, reservation_confirmed, ai_paused_to_human,
 * inactivity). KanbanAutoTransitionService (T100) consulta esta tabela
 * antes de aplicar a transição; FR-020 trava regressão sob manual override.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_pipeline_mappings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_kind', 40);
            $table->foreignId('funil_coluna_id')->constrained('funil_colunas')->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();
        });

        // Um mapping por evento por tenant.
        DB::statement('CREATE UNIQUE INDEX kanban_pipeline_mappings_tenant_event_unique ON kanban_pipeline_mappings (tenant_id, event_kind)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_pipeline_mappings');
    }
};

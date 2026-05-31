<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T013 (Fase 18 — US3, FR-022)** — audit log de toda mutação automática
 * feita no card pela IA (tool MCP) ou por listeners de eventos de domínio.
 *
 * Cobre:
 *   - Transições de status (event_kind = lead_created, slot_held, etc.) com
 *     from/to_coluna preenchidas;
 *   - Updates de perfil (event_kind = profile_updated, note_added) com
 *     field_changed + value_before/value_after (FR-021 — histórico do valor
 *     anterior consultável);
 *   - Supressões sob FR-020 (applied=false, suppression_reason).
 *
 * tool_invocation_id correlaciona com ai_tool_invocations (Fase 17 reusada)
 * quando a origem é uma capability MCP.
 *
 * Retenção: ≥6m (alinhado com ai_execution_logs da Fase 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_curation_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();

            $table->string('event_kind', 40);
            $table->string('source', 20); // ia_tool | auto_listener | manual_override_blocked

            $table->foreignId('from_coluna_id')->nullable()->constrained('funil_colunas')->nullOnDelete();
            $table->foreignId('to_coluna_id')->nullable()->constrained('funil_colunas')->nullOnDelete();

            $table->boolean('applied');
            $table->string('suppression_reason', 60)->nullable(); // manual_override|terminal_no_regress|cooldown_active

            // Para event_kind = profile_updated.
            $table->string('field_changed', 40)->nullable();
            $table->text('value_before')->nullable();
            $table->text('value_after')->nullable();

            // Correlações.
            $table->unsignedInteger('turn_version')->nullable();
            $table->foreignId('tool_invocation_id')
                ->nullable()
                ->constrained('ai_tool_invocations')
                ->nullOnDelete();

            $table->string('actor_type', 20); // ia | system | user
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('reason')->nullable();

            $table->timestampTz('created_at')->nullable();
        });

        // Timeline do card (US3 endpoint GET).
        DB::statement('CREATE INDEX kanban_curation_events_timeline_idx ON kanban_curation_events (tenant_id, paciente_id, created_at)');
        // Analytics por tipo de evento.
        DB::statement('CREATE INDEX kanban_curation_events_kind_idx ON kanban_curation_events (tenant_id, event_kind, created_at)');
        // Joins com ai_tool_invocations.
        DB::statement('CREATE INDEX kanban_curation_events_tool_idx ON kanban_curation_events (tool_invocation_id) WHERE tool_invocation_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_curation_events');
    }
};

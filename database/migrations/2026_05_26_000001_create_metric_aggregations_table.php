<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T246 (Fase 8 — Lote E US-10.1)** — Tabela `metric_aggregations` (TENANT-SCOPED).
 *
 * Pré-computação horária de KPIs por tenant (Q9). Janelas ≥ 7 dias usam
 * agregação pré-computada; janelas ≤ 24h usam queries on-demand.
 *
 * UNIQUE composto garante idempotência do job (re-execução não duplica).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §5.1
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'metric_aggregation_period_enum') THEN
                    CREATE TYPE metric_aggregation_period_enum AS ENUM ('hour', 'day', 'week', 'month');
                END IF;
            END
            $$;
        SQL);

        Schema::create('metric_aggregations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('metric_name', 100);
            // leads_by_channel, conversion_rate, no_show_rate, estimated_revenue,
            // response_time_first_p95, ai_autonomous_resolution_rate,
            // occupancy_by_professional, top_procedure_types

            $table->string('period', 10);
            $table->timestampTz('period_start');

            $table->jsonb('dimensions')->nullable();
            // {channel, professional_id, etc.}

            $table->decimal('value_numeric', 20, 4)->nullable();
            $table->jsonb('value_json')->nullable();

            $table->timestampTz('computed_at');

            $table->timestampsTz();

            $table->index(['tenant_id', 'metric_name', 'period_start'], 'idx_metric_agg_query');
            $table->index(['period', 'period_start'], 'idx_metric_agg_by_period');
        });

        DB::statement('ALTER TABLE metric_aggregations ALTER COLUMN period TYPE metric_aggregation_period_enum USING period::metric_aggregation_period_enum');

        // UNIQUE — idempotência do job (mesma métrica + período + dimensões = upsert).
        // dimensions é jsonb — usamos COALESCE para tratar NULL como '{}'.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_metric_aggregations_idempotency
            ON metric_aggregations (tenant_id, metric_name, period, period_start, COALESCE(dimensions, '{}'::jsonb))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_aggregations');
        DB::statement('DROP TYPE IF EXISTS metric_aggregation_period_enum');
    }
};

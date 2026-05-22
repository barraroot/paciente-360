<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T130 (Fase 8 — Lote B US-12.3)** — Tabela `anomalies_detected` (GLOBAL).
 *
 * Histórico de anomalias detectadas pelo cron `super-admin:detect-anomalies`
 * (Q22). 4 categorias monitoradas:
 *   - `conversion_drop`         — queda de conversão trial→pago > 20% WoW
 *   - `ai_usage_spike`          — consumo IA de um tenant > 10× média histórica
 *   - `webhook_failure_rate`    — falha de webhook > 50% em 1h (vol mínimo 10)
 *   - `payment_overdue`         — tenant inadimplente > 30 dias
 *
 * Severity {warning, critical}; critical dispara e-mail crítico ao SA além
 * de inbox interna (Q22 — inbox sempre, e-mail só em critical).
 *
 * Cooldown 30min entre alertas da mesma categoria + mesmo tenant é enforçado
 * em runtime pelo Service (não no DB) — permite resseating manual via DELETE.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §2.5
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'anomaly_category_enum') THEN
                    CREATE TYPE anomaly_category_enum AS ENUM (
                        'conversion_drop',
                        'ai_usage_spike',
                        'webhook_failure_rate',
                        'payment_overdue'
                    );
                END IF;
            END
            $$;
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'anomaly_severity_enum') THEN
                    CREATE TYPE anomaly_severity_enum AS ENUM ('warning', 'critical');
                END IF;
            END
            $$;
        SQL);

        Schema::create('anomalies_detected', function (Blueprint $table): void {
            $table->id();

            $table->string('categoria', 50);

            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            // NULL — anomalia global da plataforma (ex.: conversion_drop sistêmico).

            $table->string('severity', 20);

            $table->json('threshold_breached');
            // {metric, threshold, observed_value}

            $table->timestampTz('detected_at');
            $table->json('notified_via')->default('[]');
            // Array de canais usados: ["inbox"] OR ["inbox","email"]

            $table->timestampTz('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by_user_id')->nullable();
            $table->foreign('acknowledged_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestampTz('resolved_at')->nullable();

            $table->timestampsTz();

            $table->index(['categoria', 'severity', 'detected_at'], 'idx_anomalies_active_critical');
            // Partial index seria mais eficiente mas requer query estável — manter
            // composite simples para cobrir variações de filtro do painel.

            $table->index(['detected_at'], 'idx_anomalies_recent');
        });

        DB::statement('ALTER TABLE anomalies_detected ALTER COLUMN categoria TYPE anomaly_category_enum USING categoria::anomaly_category_enum');
        DB::statement('ALTER TABLE anomalies_detected ALTER COLUMN severity TYPE anomaly_severity_enum USING severity::anomaly_severity_enum');

        // PARTIAL INDEX por tenant — útil para filtros do painel quando tenant_id IS NOT NULL.
        DB::statement(<<<'SQL'
            CREATE INDEX idx_anomalies_by_tenant
            ON anomalies_detected (tenant_id, detected_at DESC)
            WHERE tenant_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies_detected');
        DB::statement('DROP TYPE IF EXISTS anomaly_severity_enum');
        DB::statement('DROP TYPE IF EXISTS anomaly_category_enum');
    }
};

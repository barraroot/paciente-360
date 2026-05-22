<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T085 (Fase 8 — Lote B US-12.1)** — Adiciona 5 colunas de lifecycle em `tenants`.
 *
 *  - `suspended_at`, `suspended_by_user_id`, `suspended_reason` — rastreio de
 *    quem suspendeu, quando e por quê (AC-12.1.3 + audit por motivo ≥10 chars).
 *  - `canceled_at` — quando o tenant foi cancelado (retention policy diferenciada Q20).
 *  - `retention_policy` — string identificadora; default 'differentiated_per_category'.
 *  - `billing_mode` — enum {stripe, offline_invoice} (Q23 — enterprise sales).
 *
 * Indexes parciais ajudam queries de Super Admin (listagem de suspensos /
 * cancelados / por modo de billing).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §2.6
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'tenant_billing_mode_enum') THEN
                    CREATE TYPE tenant_billing_mode_enum AS ENUM ('stripe', 'offline_invoice');
                END IF;
            END
            $$;
        SQL);

        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestampTz('suspended_at')->nullable()->after('restrictions_applied_at');

            $table->unsignedBigInteger('suspended_by_user_id')->nullable()->after('suspended_at');
            $table->foreign('suspended_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->text('suspended_reason')->nullable()->after('suspended_by_user_id');

            $table->timestampTz('canceled_at')->nullable()->after('suspended_reason');

            $table->string('retention_policy', 50)
                ->default('differentiated_per_category')
                ->after('canceled_at')
                ->comment('Q20 — política de retenção pós-cancelamento. Default categoria diferenciada.');

            $table->string('billing_mode', 20)
                ->default('stripe')
                ->after('retention_policy')
                ->comment('Q23 — modo de billing. Default stripe; offline_invoice para enterprise sales.');
        });

        // Converte billing_mode para enum nativo Postgres.
        DB::statement('ALTER TABLE tenants ALTER COLUMN billing_mode DROP DEFAULT');
        DB::statement('ALTER TABLE tenants ALTER COLUMN billing_mode TYPE tenant_billing_mode_enum USING billing_mode::tenant_billing_mode_enum');
        DB::statement("ALTER TABLE tenants ALTER COLUMN billing_mode SET DEFAULT 'stripe'");

        // PARTIAL INDEXES — apenas linhas relevantes.
        DB::statement(<<<'SQL'
            CREATE INDEX idx_tenants_suspended
            ON tenants (suspended_at)
            WHERE suspended_at IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_tenants_canceled
            ON tenants (canceled_at)
            WHERE canceled_at IS NOT NULL
        SQL);

        // CHECK — coerência entre status='suspended' e suspended_at IS NOT NULL.
        // Apenas warning conceitual; status pode estar suspended por outras causas
        // (overdue automático Fase 0) sem suspended_by/suspended_reason. Não enforcamos
        // hard CHECK para preservar compat com mecanismo automático existente.
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_tenants_suspended');
        DB::statement('DROP INDEX IF EXISTS idx_tenants_canceled');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropForeign(['suspended_by_user_id']);
            $table->dropColumn([
                'suspended_at',
                'suspended_by_user_id',
                'suspended_reason',
                'canceled_at',
                'retention_policy',
                'billing_mode',
            ]);
        });

        DB::statement('DROP TYPE IF EXISTS tenant_billing_mode_enum');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T117 (Fase 8 — Lote B US-12.2)** — Tabela `tenant_plan_bindings` (GLOBAL).
 *
 * Liga tenant a uma versão específica do plano (snapshot versioning). Quando
 * Super Admin altera plano de um tenant, cria nova binding e fecha a anterior
 * com `effective_to = now()`. Histórico fica preservado para auditoria
 * (proration Stripe, churn por plano, etc.).
 *
 * **PARTIAL UNIQUE** `(tenant_id) WHERE effective_to IS NULL` garante que
 * apenas 1 binding vigente existe por tenant.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §2.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_plan_bindings', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('plan_version_id');
            $table->foreign('plan_version_id')->references('id')->on('plan_versions')->onDelete('restrict');

            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();

            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->foreign('changed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->text('change_reason')->nullable();
            // Obrigatório (≥10 chars) quando changed_by_user_id IS NOT NULL.

            $table->timestampsTz();

            $table->index(['tenant_id', 'effective_from'], 'idx_tenant_plan_bindings_history');
        });

        // PARTIAL UNIQUE — apenas 1 binding vigente por tenant.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_tenant_plan_bindings_active
            ON tenant_plan_bindings (tenant_id)
            WHERE effective_to IS NULL
        SQL);

        // CHECK — effective_to >= effective_from quando setado.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_plan_bindings
            ADD CONSTRAINT chk_tenant_plan_bindings_range
            CHECK (effective_to IS NULL OR effective_to >= effective_from)
        SQL);

        // CHECK — change_reason obrigatório (≥10 chars) quando changed_by_user_id IS NOT NULL.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_plan_bindings
            ADD CONSTRAINT chk_tenant_plan_bindings_reason
            CHECK (
                changed_by_user_id IS NULL
                OR (change_reason IS NOT NULL AND LENGTH(TRIM(change_reason)) >= 10)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plan_bindings');
    }
};

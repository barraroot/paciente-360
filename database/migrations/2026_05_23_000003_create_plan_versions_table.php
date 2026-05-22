<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T116 (Fase 8 — Lote B US-12.2)** — Tabela `plan_versions` (GLOBAL).
 *
 * Snapshot versionado de cada plano comercial (Q12.2.2 — snapshot versioning).
 * Cada edição de plano cria nova versão; tenants existentes ficam vinculados
 * à versão original via `tenant_plan_bindings` (T117).
 *
 * **UNIQUE PARTIAL** `(plan_id) WHERE valid_to IS NULL` garante que apenas
 * 1 versão ativa existe por plano (a "current"). Edição de plano:
 *   1. UPDATE versão atual: valid_to = now()
 *   2. INSERT nova versão: valid_from = now(), valid_to = NULL
 *
 * @see specs/008-finalizacao-mvp/data-model.md §2.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('plan_id');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('restrict');

            $table->unsignedInteger('version'); // auto-incremento por plan_id (1, 2, 3...)
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();

            $table->json('snapshot');
            // Snapshot completo: {name, base_price_cents, included_professionals,
            //   included_ai_messages, daily_campaign_limit,
            //   api_rate_limit_per_minute, webhook_max_endpoints, features_enabled[]}

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            // NULL = seed inicial (T118); Super Admin user_id para edições subsequentes.

            $table->timestampsTz();

            $table->unique(['plan_id', 'version'], 'uq_plan_versions_plan_version');
            $table->index(['plan_id', 'valid_to'], 'idx_plan_versions_plan_valid');
        });

        // PARTIAL UNIQUE — apenas 1 versão ativa por plan_id (valid_to IS NULL).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_plan_versions_active_per_plan
            ON plan_versions (plan_id)
            WHERE valid_to IS NULL
        SQL);

        // CHECK — valid_to deve ser >= valid_from quando setado.
        DB::statement(<<<'SQL'
            ALTER TABLE plan_versions
            ADD CONSTRAINT chk_plan_versions_valid_range
            CHECK (valid_to IS NULL OR valid_to >= valid_from)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_versions');
    }
};

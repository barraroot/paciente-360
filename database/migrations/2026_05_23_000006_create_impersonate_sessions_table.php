<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T086 (Fase 8 — Lote B US-12.1)** — Tabela `impersonate_sessions` (GLOBAL).
 *
 * Registro de cada sessão de impersonate (Q19 — Super Admin acessando tenant
 * como suporte). PARTIAL UNIQUE `(super_admin_id) WHERE ended_at IS NULL`
 * impede duas sessões simultâneas do mesmo Super Admin.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §2.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonate_sessions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('super_admin_id');
            $table->foreign('super_admin_id')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');

            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->string('scope', 20)->default('full');
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();

            $table->unsignedInteger('screens_visited_count')->default(0);
            $table->text('reason'); // obrigatório (CHECK ≥10 chars abaixo)

            $table->timestampsTz();

            $table->index(['super_admin_id', 'started_at'], 'idx_impersonate_by_super_admin');
            $table->index(['tenant_id', 'started_at'], 'idx_impersonate_by_tenant');
        });

        // PARTIAL UNIQUE — apenas 1 sessão ATIVA por Super Admin.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_impersonate_active_per_super_admin
            ON impersonate_sessions (super_admin_id)
            WHERE ended_at IS NULL
        SQL);

        // CHECK — reason ≥10 chars (gate AC-12.1.5).
        DB::statement(<<<'SQL'
            ALTER TABLE impersonate_sessions
            ADD CONSTRAINT chk_impersonate_reason_length
            CHECK (LENGTH(TRIM(reason)) >= 10)
        SQL);

        // CHECK — duration_seconds só populado quando ended_at IS NOT NULL.
        DB::statement(<<<'SQL'
            ALTER TABLE impersonate_sessions
            ADD CONSTRAINT chk_impersonate_duration_consistency
            CHECK (
                (ended_at IS NULL AND duration_seconds IS NULL)
                OR
                (ended_at IS NOT NULL AND duration_seconds IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonate_sessions');
    }
};

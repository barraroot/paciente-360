<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T154 (Fase 8 — Lote C US-9.1)** — Tabela `campaign_dispatch_log` (TENANT-SCOPED).
 *
 * Log granular de cada tentativa de envio — diferente de `campaign_recipients`
 * (snapshot final). Permite auditoria de motivos de bloqueio em runtime
 * (Princípio VI — toda decisão de bloqueio precisa ter trilha).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §3.3
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'campaign_dispatch_result_enum') THEN
                    CREATE TYPE campaign_dispatch_result_enum AS ENUM ('sent', 'blocked', 'failed');
                END IF;
            END
            $$;
        SQL);

        Schema::create('campaign_dispatch_log', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('campaign_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('restrict');

            $table->timestampTz('attempted_at');
            $table->string('result', 20);
            $table->string('block_reason', 50)->nullable();
            $table->json('details')->nullable();

            $table->timestampsTz();

            $table->index(['campaign_id', 'attempted_at'], 'idx_campaign_dispatch_log_history');
            $table->index(['tenant_id', 'result', 'attempted_at'], 'idx_campaign_dispatch_log_compliance');
        });

        DB::statement('ALTER TABLE campaign_dispatch_log ALTER COLUMN result TYPE campaign_dispatch_result_enum USING result::campaign_dispatch_result_enum');
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_dispatch_log');
        DB::statement('DROP TYPE IF EXISTS campaign_dispatch_result_enum');
    }
};

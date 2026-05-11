<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T018 — Cria a tabela `importacoes`, estado de importações assíncronas.
 *
 * `checkpoint` guarda progresso para retry idempotente (RNF importações).
 * `relatorio` é preenchido após `status = completed | partial_failure`.
 * `arquivo_hash` SHA-256 permite detectar reenvio do mesmo arquivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('executor_id');
            $table->foreign('executor_id')->references('id')->on('users')->onDelete('restrict');

            $table->string('arquivo_path', 255);
            $table->string('arquivo_nome_original', 255);
            $table->char('arquivo_hash', 64);
            $table->unsignedBigInteger('arquivo_tamanho_bytes');

            $table->integer('total_linhas')->nullable();

            $table->string('status', 20)->default('pending');
            $table->string('status_inicial_pacientes', 10);

            $table->jsonb('checkpoint')->default('{}');
            $table->jsonb('relatorio')->nullable();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE importacoes
            ADD CONSTRAINT importacoes_status_check
            CHECK (status IN ('pending', 'processing', 'completed', 'partial_failure', 'failed', 'retrying'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE importacoes
            ADD CONSTRAINT importacoes_status_inicial_check
            CHECK (status_inicial_pacientes IN ('lead', 'ativo'))
        SQL);

        DB::statement('CREATE INDEX importacoes_tenant_status_created_idx ON importacoes (tenant_id, status, created_at DESC)');
        DB::statement('CREATE INDEX importacoes_executor_idx ON importacoes (executor_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes');
    }
};

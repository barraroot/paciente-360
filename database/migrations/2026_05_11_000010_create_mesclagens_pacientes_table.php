<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T019 — Cria a tabela `mesclagens_pacientes`, rastro reversível de merges.
 *
 * `snapshot_pre_merge` armazena o estado completo antes da mescla para
 * permitir reversão até `reversivel_ate`. Job mensal nula o snapshot após
 * `reversivel_ate + 30 dias` para reduzir tamanho JSONB.
 *
 * `pacientes_origem_ids`: array JSONB de IDs absorvidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesclagens_pacientes', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('paciente_alvo_id');
            $table->foreign('paciente_alvo_id')->references('id')->on('pacientes')->onDelete('cascade');

            $table->jsonb('pacientes_origem_ids');

            $table->unsignedBigInteger('executor_id');
            $table->foreign('executor_id')->references('id')->on('users')->onDelete('restrict');

            $table->jsonb('snapshot_pre_merge');
            $table->jsonb('resolucoes')->default('{}');

            $table->timestampTz('executada_em');
            $table->timestampTz('reversivel_ate');
            $table->timestampTz('revertida_em')->nullable();

            $table->unsignedBigInteger('revertida_por_user_id')->nullable();
            $table->foreign('revertida_por_user_id')->references('id')->on('users')->onDelete('set null');

            $table->timestampsTz();
        });

        DB::statement('CREATE INDEX mesclagens_tenant_alvo_idx ON mesclagens_pacientes (tenant_id, paciente_alvo_id)');
        DB::statement('CREATE INDEX mesclagens_tenant_reversivel_idx ON mesclagens_pacientes (tenant_id, reversivel_ate, revertida_em)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mesclagens_pacientes');
    }
};

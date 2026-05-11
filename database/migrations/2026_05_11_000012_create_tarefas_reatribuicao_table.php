<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T021 — Cria a tabela `tarefas_reatribuicao`, backlog de pacientes órfãos
 * gerado ao desativar um profissional.
 *
 * `total_pacientes` é denormalizado (count de `pacientes_orfaos_ids`) para
 * listagem rápida sem precisar de jsonb_array_length em runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarefas_reatribuicao', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('profissional_desativado_id');
            $table->foreign('profissional_desativado_id')
                ->references('id')->on('professionals')->onDelete('restrict');

            $table->jsonb('pacientes_orfaos_ids');
            $table->integer('total_pacientes');

            $table->timestampTz('criada_em');
            $table->timestampTz('concluida_em')->nullable();

            $table->unsignedBigInteger('concluida_por_user_id')->nullable();
            $table->foreign('concluida_por_user_id')->references('id')->on('users')->onDelete('set null');

            $table->timestampsTz();
        });

        DB::statement('CREATE INDEX tarefas_reatribuicao_tenant_concluida_idx ON tarefas_reatribuicao (tenant_id, concluida_em)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tarefas_reatribuicao');
    }
};

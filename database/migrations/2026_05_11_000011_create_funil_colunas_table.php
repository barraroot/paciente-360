<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T020 — Cria a tabela `funil_colunas` (Kanban) e adiciona FK diferida
 * em `pacientes.funil_coluna_atual_id`.
 *
 * UNIQUE (tenant_id, posicao): garante que reordenamento deve ser feito
 * via swap explícito, sem colisões silenciosas.
 *
 * Ao final, adiciona a FK em `pacientes.funil_coluna_atual_id` que foi
 * deixada sem FK em T011 pois a tabela ainda não existia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funil_colunas', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('nome', 50);
            $table->string('slug', 50);
            $table->integer('posicao');
            $table->string('cor', 7)->nullable();

            $table->boolean('is_terminal')->default(false);
            $table->boolean('motivo_obrigatorio')->default(false);
            $table->boolean('is_system')->default(false);

            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX funil_colunas_tenant_slug_unique ON funil_colunas (tenant_id, slug)');
        DB::statement('CREATE UNIQUE INDEX funil_colunas_tenant_posicao_unique ON funil_colunas (tenant_id, posicao)');
        DB::statement('CREATE INDEX funil_colunas_tenant_posicao_idx ON funil_colunas (tenant_id, posicao)');

        // FK diferida: agora que funil_colunas existe, adiciona a FK em
        // pacientes.funil_coluna_atual_id que foi deixada sem FK em T011.
        DB::statement(<<<'SQL'
            ALTER TABLE pacientes
            ADD CONSTRAINT pacientes_funil_coluna_atual_id_fkey
            FOREIGN KEY (funil_coluna_atual_id)
            REFERENCES funil_colunas(id)
            ON DELETE SET NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pacientes DROP CONSTRAINT IF EXISTS pacientes_funil_coluna_atual_id_fkey');
        Schema::dropIfExists('funil_colunas');
    }
};

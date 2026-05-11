<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T014 — Cria a tabela `tags`, catálogo de tags por tenant.
 *
 * `nome_normalizado` = `lower(unaccent(nome))` — calculado na camada de
 * aplicação (Service) antes de persistir, diferente das colunas GENERATED
 * em `pacientes`. O UNIQUE é em `nome_normalizado` para que tags que diferem
 * apenas em caixa/acento não sejam duplicadas.
 *
 * `tipo` CHECK IN ('livre', 'sistemica'): tags sistêmicas têm prefixo `sys:`
 * e não podem ser criadas por usuários comuns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('nome', 50);
            $table->string('nome_normalizado', 50);
            $table->string('tipo', 10)->default('livre');
            $table->string('cor', 7)->nullable();
            $table->string('descricao', 255)->nullable();

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tags
            ADD CONSTRAINT tags_tipo_check
            CHECK (tipo IN ('livre', 'sistemica'))
        SQL);

        DB::statement('CREATE UNIQUE INDEX tags_tenant_nome_normalizado_unique ON tags (tenant_id, nome_normalizado)');
        DB::statement('CREATE INDEX tags_tenant_tipo_idx ON tags (tenant_id, tipo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};

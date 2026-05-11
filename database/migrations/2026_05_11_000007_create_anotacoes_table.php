<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T016 — Cria a tabela `anotacoes` com trigger de imutabilidade.
 *
 * Anotações são append-only: UPDATE e DELETE são rejeitados tanto pelo
 * trigger PG (defesa em profundidade) quanto pelo Model boot (T026).
 *
 * Retratação é implementada como NOVA anotação com `retratacao_de_anotacao_id`
 * preenchido — nunca editando o registro original.
 *
 * SEM `updated_at`: registros imutáveis não têm campo de atualização.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anotacoes', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('paciente_id');
            $table->foreign('paciente_id')->references('id')->on('pacientes')->onDelete('cascade');

            $table->string('tipo', 20);

            $table->text('texto');

            $table->unsignedBigInteger('autor_id');
            $table->foreign('autor_id')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('retratacao_de_anotacao_id')->nullable();
            $table->foreign('retratacao_de_anotacao_id')
                ->references('id')->on('anotacoes')->onDelete('restrict');

            $table->timestampTz('created_at')->useCurrent();
            // Sem updated_at: imutável.
        });

        DB::statement(<<<'SQL'
            ALTER TABLE anotacoes
            ADD CONSTRAINT anotacoes_tipo_check
            CHECK (tipo IN ('geral', 'clinica', 'comportamental', 'financeira'))
        SQL);

        DB::statement('CREATE INDEX anotacoes_tenant_paciente_created_idx ON anotacoes (tenant_id, paciente_id, created_at DESC)');
        DB::statement('CREATE INDEX anotacoes_tipo_idx ON anotacoes (tipo)');

        // Trigger de imutabilidade: rejeita UPDATE e DELETE no banco,
        // independentemente do cliente que executa a query.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION anotacoes_immutable_trigger_fn()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'anotacoes is append-only: UPDATE and DELETE are not permitted';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER anotacoes_immutable_trigger
                BEFORE UPDATE OR DELETE ON anotacoes
                FOR EACH ROW EXECUTE FUNCTION anotacoes_immutable_trigger_fn()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS anotacoes_immutable_trigger ON anotacoes');
        DB::statement('DROP FUNCTION IF EXISTS anotacoes_immutable_trigger_fn()');
        Schema::dropIfExists('anotacoes');
    }
};

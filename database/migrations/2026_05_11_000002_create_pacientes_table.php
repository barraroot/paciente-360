<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T011 — Cria a tabela `pacientes`, entidade central do CRM.
 *
 * Pontos críticos de schema:
 *  - `nome_normalizado` e `telefone_primario_normalizado` são colunas GENERATED ALWAYS
 *    (computed no banco), não suportadas nativamente pelo Schema Builder —
 *    adicionadas via `ALTER TABLE ... ADD COLUMN ... GENERATED ALWAYS AS ... STORED`.
 *  - `cpf` tem UNIQUE parcial `WHERE cpf IS NOT NULL` para permitir múltiplos
 *    pacientes sem CPF no mesmo tenant.
 *  - FKs para `convenio_principal_id` (→ paciente_convenios) e `funil_coluna_atual_id`
 *    (→ funil_colunas) são adicionadas DEPOIS que essas tabelas existirem (T013 e T020).
 *  - `merged_into_paciente_id` é auto-referência na própria tabela.
 *  - Índices compostos sempre começam por `tenant_id` (RNF-001 / DBA mandate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Dados pessoais
            $table->string('nome', 150);
            // nome_normalizado: GENERATED — adicionado via ALTER TABLE abaixo
            $table->string('cpf', 14)->nullable();
            $table->string('documento_estrangeiro', 30)->nullable();
            $table->date('data_nascimento')->nullable();

            $table->string('telefone_primario', 20)->nullable();
            // telefone_primario_normalizado: GENERATED — adicionado via ALTER TABLE abaixo
            $table->jsonb('telefones_secundarios')->default('[]');

            $table->string('email', 254)->nullable();
            $table->jsonb('endereco')->nullable();

            // Estado / origem
            $table->string('status', 20)->default('lead');
            $table->string('origem', 20)->default('outro');
            $table->string('origem_detalhe', 255)->nullable();
            $table->string('origem_origem', 10)->default('manual');

            // Relações de negócio (FKs para entidades externas ou auto-ref)
            $table->unsignedBigInteger('profissional_responsavel_id')->nullable();
            $table->foreign('profissional_responsavel_id')
                ->references('id')->on('professionals')->onDelete('set null');

            // convenio_principal_id → paciente_convenios: FK adicionada em T013
            $table->unsignedBigInteger('convenio_principal_id')->nullable();

            // funil_coluna_atual_id → funil_colunas: FK adicionada em T020
            $table->unsignedBigInteger('funil_coluna_atual_id')->nullable();
            $table->decimal('funil_posicao', 20, 10)->nullable();

            // LGPD / mescla
            $table->timestampTz('anonimizado_em')->nullable();

            $table->unsignedBigInteger('merged_into_paciente_id')->nullable();
            $table->foreign('merged_into_paciente_id')
                ->references('id')->on('pacientes')->onDelete('set null');
            $table->timestampTz('merged_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Colunas GENERATED ALWAYS AS STORED (não suportadas pelo Schema Builder).
        // Usa `immutable_unaccent()` — wrapper IMMUTABLE criada em T010 —
        // pois `unaccent()` nativa não é IMMUTABLE e não pode ser usada em
        // colunas GENERATED.
        DB::statement(<<<'SQL'
            ALTER TABLE pacientes
            ADD COLUMN nome_normalizado VARCHAR(150)
            GENERATED ALWAYS AS (lower(immutable_unaccent(nome))) STORED
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE pacientes
            ADD COLUMN telefone_primario_normalizado VARCHAR(20)
            GENERATED ALWAYS AS (regexp_replace(coalesce(telefone_primario, ''), '\D', '', 'g')) STORED
        SQL);

        // CHECK constraints (emulam ENUM para extensibilidade futura sem DDL)
        DB::statement(<<<'SQL'
            ALTER TABLE pacientes
            ADD CONSTRAINT pacientes_status_check
            CHECK (status IN ('lead', 'ativo', 'inativo', 'bloqueado'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE pacientes
            ADD CONSTRAINT pacientes_origem_check
            CHECK (origem IN ('site', 'indicacao', 'whatsapp', 'instagram', 'telefone', 'presencial', 'outro'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE pacientes
            ADD CONSTRAINT pacientes_origem_origem_check
            CHECK (origem_origem IN ('manual', 'canal'))
        SQL);

        // UNIQUE parcial: CPF único por tenant, mas NULL permitido múltiplas vezes
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX pacientes_cpf_tenant_unique
            ON pacientes (cpf, tenant_id)
            WHERE cpf IS NOT NULL
        SQL);

        // Índices compostos tenant-first para queries do painel
        DB::statement('CREATE INDEX pacientes_tenant_status_idx ON pacientes (tenant_id, status)');
        DB::statement('CREATE INDEX pacientes_tenant_origem_idx ON pacientes (tenant_id, origem)');
        DB::statement('CREATE INDEX pacientes_tenant_profissional_idx ON pacientes (tenant_id, profissional_responsavel_id)');
        DB::statement('CREATE INDEX pacientes_tenant_funil_idx ON pacientes (tenant_id, funil_coluna_atual_id, funil_posicao)');
        DB::statement('CREATE INDEX pacientes_tenant_anonimizado_idx ON pacientes (tenant_id, anonimizado_em)');
        DB::statement('CREATE INDEX pacientes_merged_into_idx ON pacientes (merged_into_paciente_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};

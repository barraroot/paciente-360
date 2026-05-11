<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T022 — Adiciona índices GIN trigram para busca por similaridade em `pacientes`.
 *
 * Usa `btree_gin` para suportar GIN composto com coluna `tenant_id` (btree)
 * + coluna trigram. Isso permite que a query filtre `tenant_id = ?` e use
 * o GIN para busca de similaridade simultaneamente, sem precisar de dois
 * index scans separados.
 *
 * Referência: https://www.postgresql.org/docs/current/btree-gin.html
 */
return new class extends Migration
{
    public function up(): void
    {
        // btree_gin: permite combinar operadores btree (=, <, >) com GIN
        // em índices compostos. Necessário para o GIN composto (tenant_id, coluna_trgm).
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gin');

        DB::statement(<<<'SQL'
            CREATE INDEX pacientes_nome_trgm_idx
            ON pacientes USING GIN (tenant_id, nome_normalizado gin_trgm_ops)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX pacientes_telefone_trgm_idx
            ON pacientes USING GIN (tenant_id, telefone_primario_normalizado gin_trgm_ops)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pacientes_nome_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS pacientes_telefone_trgm_idx');
        DB::statement('DROP EXTENSION IF EXISTS btree_gin');
    }
};

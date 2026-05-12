<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T023 — Adiciona índices GIN trigram e BRIN em `messaging_messages`.
 *
 * Separado em migration própria para permitir reindex isolado
 * e facilitar rollback sem afetar o schema da tabela.
 *
 * GIN composto (tenant_id, body_searchable_normalized gin_trgm_ops):
 *  - `btree_gin` (habilitada na Fase 2) permite combinar btree em tenant_id
 *    com GIN trigram em body_searchable_normalized no mesmo índice.
 *  - Isolamento por tenant: PG usa tenant_id para reduzir o scan do GIN.
 *  - Suporta buscas por similaridade (NC-13/R5): p95 < 500ms para 50k conversas.
 *  - Nunca indexa `body` cifrado — apenas `body_searchable_normalized` (plain).
 *
 * BRIN em created_at:
 *  - Tabela append-only — BRIN é muito eficiente (correlação natural por insert).
 *  - Viabiliza archive mensal de mensagens > 2 anos (retenção NC-14).
 *  - Tamanho negligenciável comparado a B-tree.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX messages_body_trgm_idx
            ON messaging_messages USING GIN (tenant_id, body_searchable_normalized gin_trgm_ops)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX messages_created_at_brin_idx
            ON messaging_messages USING BRIN (created_at)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messages_body_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS messages_created_at_brin_idx');
    }
};

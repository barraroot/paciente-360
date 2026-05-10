<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `password_reset_tokens` — multi-tenant aware.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 8.
 *
 * Notas:
 *  - PK composto `(email, tenant_id)` não pode ser definido via Blueprint
 *    quando tenant_id é NULLABLE, pois NULL não é comparável em PKs B-tree
 *    do PG (dois NULLs são distintos — a PK não seria realmente única para
 *    Super Admins). Solução: sem PK nativa; usamos UNIQUE INDEX com
 *    COALESCE(tenant_id, 0) como sentinela, idêntico ao padrão de `users`.
 *  - A tabela NÃO usa `id` auto-increment: semanticamente é uma tabela de
 *    estado "um token por (email, tenant)" — um novo request de reset
 *    substitui o anterior via upsert no Service (T080).
 *  - `token` armazena o hash (nunca o valor em claro — compatível com
 *    `Password::sendResetLink` que já grava o hash).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                email      VARCHAR(254) NOT NULL,
                tenant_id  BIGINT       NULL
                               REFERENCES tenants(id) ON DELETE CASCADE,
                token      VARCHAR(255) NOT NULL,
                created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        SQL);

        // Unicidade do par (email, tenant) com COALESCE para tratar NULL
        // (Super Admin) como sentinela 0 e evitar duplicatas silenciosas.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX password_reset_tokens_email_tenant_unique
            ON password_reset_tokens (email, COALESCE(tenant_id, 0))
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX password_reset_tokens_tenant_id_idx
            ON password_reset_tokens (tenant_id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T010 — Habilita extensões PostgreSQL para busca por similaridade (pg_trgm)
 * e normalização de acentos (unaccent).
 *
 * Ambas são necessárias para os índices GIN trigram em `pacientes` e para
 * as colunas GENERATED `nome_normalizado` e `telefone_primario_normalizado`.
 *
 * Idempotente via `IF NOT EXISTS` / `IF EXISTS`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        // `unaccent()` não é IMMUTABLE por padrão no PostgreSQL, o que
        // impede seu uso em colunas GENERATED ALWAYS AS STORED.
        // A solução padrão é criar uma wrapper IMMUTABLE que chama a função
        // original — o PostgreSQL aceita isso para usos com textos simples.
        // Ref: https://www.postgresql.org/docs/current/unaccent.html
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE
            RETURNS NULL ON NULL INPUT
            AS $$ SELECT unaccent($1) $$
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
        DB::statement('DROP EXTENSION IF EXISTS unaccent');
    }
};

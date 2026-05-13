<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Novos índices em personal_access_tokens para performance.
 *
 * Índice composto (tokenable_type, tokenable_id, expires_at):
 *   Otimiza GET /auth/tokens — lista tokens ativos por user sem scan full-table.
 *   Sanctum já cria (tokenable_type, tokenable_id); este adiciona expires_at
 *   para evitar filter-apply após index scan no lookup de tokens válidos.
 *
 * Índice simples (expires_at) WHERE expires_at IS NOT NULL:
 *   Otimiza purge job auth:tokens-purge-expired (T090) que filtra por
 *   WHERE expires_at < now() - interval '90 days'. PostgreSQL usa range scan.
 *   Nota: não usar now() em predicado de partial index — não é IMMUTABLE.
 *   Usa IS NOT NULL como predicado seguro; planner aplica range scan no WHERE da query.
 *
 * Compatível com Sanctum — não altera schema, apenas adiciona índices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->index(
                ['tokenable_type', 'tokenable_id', 'expires_at'],
                'pat_tokenable_expires_idx'
            );
        });

        // Partial index para purge job — NOT NULL como predicado IMMUTABLE-safe
        DB::statement(
            'CREATE INDEX IF NOT EXISTS pat_expires_at_idx
             ON personal_access_tokens (expires_at)
             WHERE expires_at IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex('pat_tokenable_expires_idx');
        });

        DB::statement('DROP INDEX IF EXISTS pat_expires_at_idx');
    }
};

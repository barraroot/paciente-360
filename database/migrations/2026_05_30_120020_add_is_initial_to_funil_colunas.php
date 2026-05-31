<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T020 (Fase 18 — US2)** — flag is_initial em funil_colunas.
 *
 * Necessário para FR-011/FR-013 não dependerem de slug literal `'new'`
 * (admin pode renomear a coluna inicial). Exatamente 1 coluna por tenant
 * tem `is_initial=true` — garantido por UNIQUE parcial.
 *
 * Backfill por tenant: marca como `is_initial=true` a coluna com slug='new'
 * (case-insensitive); fallback: primeira por `posicao` ASC. Tenants sem
 * colunas (estado degenerado) serão tratados pelo
 * DefaultKanbanPipelineMappingSeeder (T035).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funil_colunas', function (Blueprint $table): void {
            $table->boolean('is_initial')->default(false);
        });

        DB::statement('CREATE UNIQUE INDEX funil_colunas_tenant_initial_unique ON funil_colunas (tenant_id) WHERE is_initial = true');

        // Backfill: para cada tenant, escolher a coluna `slug='new'`; se não
        // existir, a primeira por `posicao` ASC. Idempotente (só promove
        // se ninguém é initial naquele tenant).
        DB::statement(<<<'SQL'
            WITH chosen AS (
                SELECT DISTINCT ON (tenant_id) id, tenant_id
                FROM funil_colunas
                ORDER BY tenant_id,
                         (LOWER(slug) = 'new') DESC,
                         posicao ASC
            )
            UPDATE funil_colunas fc
            SET is_initial = true
            FROM chosen
            WHERE fc.id = chosen.id
              AND NOT EXISTS (
                  SELECT 1 FROM funil_colunas fc2
                  WHERE fc2.tenant_id = chosen.tenant_id
                    AND fc2.is_initial = true
              );
        SQL);
    }

    public function down(): void
    {
        Schema::table('funil_colunas', function (Blueprint $table): void {
            $table->dropColumn('is_initial');
        });
    }
};

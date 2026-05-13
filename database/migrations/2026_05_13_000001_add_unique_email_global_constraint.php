<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Troca constraint UNIQUE composta (tenant_id, email) por UNIQUE global (email).
 *
 * PRÉ-REQUISITO OBRIGATÓRIO: rodar users:dedupe-emails-cross-tenant --check antes.
 * Esta migration aborta com RuntimeException se houver duplicatas, protegendo integridade.
 *
 * O índice antigo usa expressão funcional COALESCE(tenant_id, 0) para aceitar
 * Super Admins (tenant_id NULL). O rollback restaura esse comportamento.
 *
 * Justificativa: FR-001a — login resolve tenant via lookup global de users.email.
 * Sem UNIQUE global, query pode retornar múltiplos rows gerando ambiguidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pre-flight: abortar se duplicatas presentes (gate obrigatório)
        $duplicates = DB::table('users')
            ->select('email')
            ->whereNull('deleted_at')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException(
                "Migration abortada: {$duplicates} emails duplicados encontrados cross-tenant. ".
                'Execute primeiro: php artisan users:dedupe-emails-cross-tenant --check (ou --auto)'
            );
        }

        // Remove índice antigo (expressão funcional com COALESCE)
        DB::statement('DROP INDEX IF EXISTS users_email_tenant_unique');

        // Cria UNIQUE global simples (email único em todos os tenants)
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email', 'users_email_unique');
        });
    }

    public function down(): void
    {
        // Remove índice global
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });

        // Restaura índice funcional com COALESCE que suporta Super Admins (tenant_id NULL)
        DB::statement('CREATE UNIQUE INDEX users_email_tenant_unique ON users (email, COALESCE(tenant_id, 0))');
    }
};

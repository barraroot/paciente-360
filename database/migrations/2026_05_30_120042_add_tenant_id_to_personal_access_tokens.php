<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T042 (Fase 18 — US7, FR-046)** — adiciona `tenant_id` em
 * `personal_access_tokens` (Sanctum).
 *
 * Necessário para o McpTokenGuard (T041) resolver o tenant DA CREDENCIAL,
 * não de input do cliente. NULL para tokens criados antes desta fase
 * (compat: o guard SPA Bearer já infere tenant via `tokenable.tenant_id`
 * do User, mas o MCP precisa de tokens scoped a tenant que NÃO pertencem
 * a um User humano — ex.: token emitido por Persona Test Session).
 *
 * Backfill em down(): nada — coluna apenas adicionada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('tokenable_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        DB::statement('CREATE INDEX personal_access_tokens_tenant_id_idx ON personal_access_tokens (tenant_id) WHERE tenant_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

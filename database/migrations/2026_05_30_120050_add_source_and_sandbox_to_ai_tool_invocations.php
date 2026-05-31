<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T050a (Fase 18 — US7, FR-049)** — adiciona `source` e `sandbox` em
 * `ai_tool_invocations` (Fase 17 reusada).
 *
 * - `source` distingue caminho de execução: `native` (laravel/ai Tools Fase 17,
 *   fallback runtime) vs `mcp` (servidor MCP local Fase 18, caminho de
 *   produção sob `AI_TOOLS_VIA_MCP=true`).
 * - `sandbox` distingue execuções de chat de teste de Persona (US6) das
 *   produção — agregadores devem filtrar por `sandbox=false` (FR-042).
 *
 * Backfill: linhas existentes ficam com `source='native'` e `sandbox=false`
 * (defaults), refletindo a realidade pré-Fase 18.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tool_invocations', function (Blueprint $table): void {
            $table->string('source', 16)->default('native')->after('tool_name');
            $table->boolean('sandbox')->default(false)->after('source');
        });

        // Analytics: separar invocações MCP vs nativas.
        DB::statement('CREATE INDEX ai_tool_invocations_source_idx ON ai_tool_invocations (source, sandbox)');
    }

    public function down(): void
    {
        Schema::table('ai_tool_invocations', function (Blueprint $table): void {
            $table->dropColumn(['source', 'sandbox']);
        });
    }
};

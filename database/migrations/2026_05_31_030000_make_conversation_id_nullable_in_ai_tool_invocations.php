<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **Fase 18 — US7, FR-049** — `McpCallLogger` precisa registrar invocações
 * MCP feitas por clientes externos (Claude Desktop, sandbox de Persona Test)
 * que não estão associadas a uma `messaging_conversations`. A coluna
 * `conversation_id` foi declarada NOT NULL na Fase 17 (apenas tools nativas
 * chamadas no contexto de turno). Agora precisa ser nullable.
 *
 * Aditiva e idempotente; FK `cascadeOnDelete` preservada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ai_tool_invocations ALTER COLUMN conversation_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Não revertemos — fazer NOT NULL com linhas nulas existentes quebraria.
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T016 (Fase 18 — US7, FR-053b/c/d)** — snapshot histórico de transições
 * do circuit breaker do MCP. Analytics + auditoria.
 *
 * O estado VIVO é Redis (mcp:cb:*) — esta tabela registra cada abertura/
 * fechamento (Listener PersistMcpCircuitSnapshotListener — T054).
 *
 * `source` distingue:
 *   - automatic — disparado pelo McpCircuitBreaker (3 falhas em 30s, etc.)
 *   - manual_flag — admin mudou AI_TOOLS_VIA_MCP=false (rollback operacional)
 *
 * SEM tenant_id — é estado global do MCP (1 servidor por instalação).
 * Retenção: 90 dias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_circuit_breaker_snapshots', function (Blueprint $table): void {
            $table->id();

            $table->string('transition_to', 20); // open | half_open | closed

            $table->unsignedInteger('failures_observed');
            $table->unsignedInteger('cooldown_seconds');

            $table->string('last_error_code', 60)->nullable();
            $table->text('last_error_message')->nullable();

            $table->string('source', 20); // automatic | manual_flag
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampTz('created_at')->nullable();
        });

        DB::statement('CREATE INDEX mcp_circuit_breaker_snapshots_recent_idx ON mcp_circuit_breaker_snapshots (created_at DESC)');
        DB::statement('CREATE INDEX mcp_circuit_breaker_snapshots_state_idx ON mcp_circuit_breaker_snapshots (transition_to, created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_circuit_breaker_snapshots');
    }
};

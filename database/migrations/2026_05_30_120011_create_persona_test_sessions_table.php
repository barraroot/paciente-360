<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T011 (Fase 18 — US6)** — sessões isoladas do chat de teste de Persona.
 *
 * Cada sessão pertence a um admin (FR-043 — isolamento por usuário) e
 * carrega um `persona_snapshot` (FR-039 — versão publicada OU draft em edição).
 *
 * `mcp_token_id` aponta para um Sanctum PAT emitido com ability `mcp.invoke`
 * + metadata `sandbox=true`; revogado no `close()` (FR-051).
 *
 * Mensagens da sessão usam `messaging_messages` com `sandbox=true`
 * + `sandbox_session_id` (T018) — evita schema duplicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona_test_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('ai_personas')->cascadeOnDelete();

            $table->jsonb('persona_snapshot');

            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('mcp_token_id')
                ->nullable()
                ->constrained('personal_access_tokens')
                ->nullOnDelete();

            $table->string('status', 20)->default('open'); // open | closed | archived
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->timestampsTz();
        });

        // Listagem por admin (FR-043).
        DB::statement('CREATE INDEX persona_test_sessions_admin_status_idx ON persona_test_sessions (tenant_id, admin_user_id, status)');
        // Todas as sessões de uma persona.
        DB::statement('CREATE INDEX persona_test_sessions_persona_idx ON persona_test_sessions (persona_id, created_at)');
        // 1 sessão `open` simultânea por (admin, persona) — superseded auto-fecha (T175).
        DB::statement('CREATE UNIQUE INDEX persona_test_sessions_one_open_per_admin_persona_unique ON persona_test_sessions (admin_user_id, persona_id) WHERE status = \'open\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_test_sessions');
    }
};

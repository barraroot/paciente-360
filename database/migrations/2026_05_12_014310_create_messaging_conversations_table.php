<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T013 — Cria a tabela `messaging_conversations`.
 *
 * Stream contínuo por par (paciente, canal). Uma conversa por paciente/canal durante
 * toda a vida útil (decisão NC-2). Alta volumetria — até 50k linhas por tenant.
 *
 * Pontos críticos:
 *  - `external_thread_id_normalized` é coluna GENERATED ALWAYS AS STORED — adicionada
 *    via ALTER TABLE pois o Schema Builder não suporta GENERATED em PG.
 *  - UNIQUE composto usa a coluna normalizada para dedup case-insensitive sem trigger.
 *  - 6 índices cobrem as queries dominantes da inbox.
 *  - FK channel_id usa RESTRICT (não permite excluir canal com conversas ativas).
 *  - FKs patient_id, assigned_user_id, ai_pause_set_by usam SET NULL (nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_conversations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('channel_id');
            $table->foreign('channel_id')->references('id')->on('messaging_channels')->onDelete('restrict');

            $table->unsignedBigInteger('patient_id')->nullable();
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('set null');

            $table->string('external_thread_id', 255);
            // external_thread_id_normalized: adicionada abaixo via ALTER TABLE (GENERATED)

            $table->string('status', 20)->default('aberta');

            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');

            $table->timestampTz('assigned_at')->nullable();
            $table->string('assignment_strategy', 30)->nullable();

            $table->timestampTz('ai_paused_until')->nullable();

            $table->unsignedBigInteger('ai_pause_set_by')->nullable();
            $table->foreign('ai_pause_set_by')->references('id')->on('users')->onDelete('set null');

            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('last_inbound_message_at')->nullable();

            $table->timestampTz('opened_at')->default(DB::raw('now()'));
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolution_mode', 20)->nullable();

            $table->string('priority', 10)->default('normal');
            $table->boolean('received_outside_hours')->default(false);
            $table->integer('unread_count')->default(0);

            $table->timestampsTz();
        });

        // Coluna GENERATED ALWAYS AS STORED — não suportada pelo Schema Builder
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_conversations
            ADD COLUMN external_thread_id_normalized VARCHAR(255)
            GENERATED ALWAYS AS (lower(external_thread_id)) STORED
        SQL);

        // CHECK constraints
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_conversations
            ADD CONSTRAINT messaging_conversations_status_check
            CHECK (status IN ('aberta', 'pendente', 'resolvida', 'reaberta'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_conversations
            ADD CONSTRAINT messaging_conversations_assignment_strategy_check
            CHECK (assignment_strategy IS NULL OR assignment_strategy IN ('manual', 'auto_round_robin', 'auto_patient_owner', 'transfer'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_conversations
            ADD CONSTRAINT messaging_conversations_resolution_mode_check
            CHECK (resolution_mode IS NULL OR resolution_mode IN ('manual', 'auto_inatividade'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_conversations
            ADD CONSTRAINT messaging_conversations_priority_check
            CHECK (priority IN ('alta', 'normal', 'baixa'))
        SQL);

        // UNIQUE: 1 conversa por par (tenant, canal, external_thread) — decisão NC-2
        // Usa coluna normalizada para dedup case-insensitive
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_conversations_thread_unique
            ON messaging_conversations (tenant_id, channel_id, external_thread_id_normalized)
        SQL);

        // Query dominante: inbox listing — filtrado por status, ordenado por recência (80% das queries)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conversations_tenant_status_last_msg_idx
            ON messaging_conversations (tenant_id, status, last_message_at DESC)
        SQL);

        // "Minhas conversas" — atendente vê apenas as suas
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conversations_tenant_assigned_status_idx
            ON messaging_conversations (tenant_id, assigned_user_id, status)
        SQL);

        // Ficha do paciente — timeline de conversas (partial: exclui não-identificados)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conversations_tenant_patient_idx
            ON messaging_conversations (tenant_id, patient_id, last_message_at DESC)
            WHERE patient_id IS NOT NULL
        SQL);

        // Job de expiração de pausa de IA
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conversations_ai_paused_idx
            ON messaging_conversations (tenant_id, ai_paused_until)
            WHERE ai_paused_until IS NOT NULL
        SQL);

        // Auto-resolve job: conversas abertas/pendentes sem atividade
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conversations_auto_resolve_idx
            ON messaging_conversations (tenant_id, status, last_message_at)
            WHERE status IN ('aberta', 'pendente')
        SQL);

        // Fila "Sem atendente" — partial index global (sem tenant_id — scan de todas as filas)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conversations_unassigned_idx
            ON messaging_conversations (assigned_user_id)
            WHERE assigned_user_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_conversations');
    }
};

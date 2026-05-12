<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T014 — Cria a tabela `messaging_conversation_assignments`.
 *
 * Histórico imutável de atribuições e transferências (append-only).
 * Cada transferência cria nova linha; `unassigned_at` é preenchido quando
 * a próxima atribuição é criada.
 *
 * FKs user_id e assigned_by usam RESTRICT para preservar histórico de auditoria.
 * NULL em user_id = transferência para fila "Sem atendente".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_conversation_assignments', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('conversation_id');
            $table->foreign('conversation_id')->references('id')->on('messaging_conversations')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('restrict');

            $table->string('assignment_role', 30)->nullable();
            $table->timestampTz('assigned_at')->default(DB::raw('now()'));
            $table->timestampTz('unassigned_at')->nullable();
            $table->text('transfer_note')->nullable();
            $table->string('reason', 50)->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_conversation_assignments
            ADD CONSTRAINT messaging_conversation_assignments_reason_check
            CHECK (reason IS NULL OR reason IN ('inicial', 'manual', 'transferencia', 'reassign_offline', 'auto_atribuicao'))
        SQL);

        // Histórico de uma conversa em ordem cronológica reversa
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conv_assignments_conv_assigned_at_idx
            ON messaging_conversation_assignments (tenant_id, conversation_id, assigned_at DESC)
        SQL);

        // "Conversas atualmente atribuídas a este usuário" (partial: só ativas)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_conv_assignments_user_active_idx
            ON messaging_conversation_assignments (tenant_id, user_id, unassigned_at)
            WHERE unassigned_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_conversation_assignments');
    }
};

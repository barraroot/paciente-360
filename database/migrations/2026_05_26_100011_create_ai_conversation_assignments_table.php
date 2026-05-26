<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atribuição de persona à conversa EXISTENTE (messaging_conversations).
 * UNIQUE parcial garante uma atribuição ativa por conversa (continuidade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation_assignments', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('conversation_id');
            $table->foreign('conversation_id')->references('id')->on('messaging_conversations')->onDelete('cascade');

            $table->string('channel_type', 20);

            $table->unsignedBigInteger('ai_persona_id');
            $table->foreign('ai_persona_id')->references('id')->on('ai_personas')->onDelete('restrict');

            $table->string('status', 20)->default('assigned');

            $table->timestampTz('assigned_at');
            $table->timestampTz('unassigned_at')->nullable();

            $table->unsignedBigInteger('paused_by')->nullable();
            $table->foreign('paused_by')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('paused_at')->nullable();

            $table->jsonb('metadata')->default('{}');

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ai_conversation_assignments
            ADD CONSTRAINT ai_conversation_assignments_status_check
            CHECK (status IN ('assigned', 'in_progress', 'paused', 'closed', 'error'))
        SQL);

        // Uma atribuição NÃO encerrada por conversa.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ai_conversation_assignments_active_unique
            ON ai_conversation_assignments (conversation_id)
            WHERE status <> 'closed'
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX ai_conversation_assignments_tenant_persona_idx
            ON ai_conversation_assignments (tenant_id, ai_persona_id, status)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_assignments');
    }
};

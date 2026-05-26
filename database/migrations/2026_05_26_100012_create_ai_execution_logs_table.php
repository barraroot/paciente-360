<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Log auditável por decisão de IA (Princípios III/V). Conteúdo já
 * pseudonimizado; retenção ≥ 6 meses (governada por política de retenção).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_execution_logs', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->foreign('conversation_id')->references('id')->on('messaging_conversations')->nullOnDelete();

            $table->unsignedBigInteger('message_id')->nullable();
            $table->foreign('message_id')->references('id')->on('messaging_messages')->nullOnDelete();

            $table->unsignedBigInteger('response_message_id')->nullable();
            $table->foreign('response_message_id')->references('id')->on('messaging_messages')->nullOnDelete();

            $table->unsignedBigInteger('ai_persona_id')->nullable();
            $table->foreign('ai_persona_id')->references('id')->on('ai_personas')->nullOnDelete();

            $table->unsignedBigInteger('ai_model_id')->nullable();
            $table->foreign('ai_model_id')->references('id')->on('ai_models')->nullOnDelete();

            $table->string('channel_type', 20)->nullable();
            $table->uuid('correlation_id');

            $table->text('prompt_summary')->nullable();
            $table->jsonb('context_summary')->nullable();
            $table->string('classified_intent', 60)->nullable();
            $table->decimal('confidence_score', 4, 3)->nullable();
            $table->text('response_summary')->nullable();
            $table->string('action', 40)->nullable();
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'conversation_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ai_execution_logs
            ADD CONSTRAINT ai_execution_logs_status_check
            CHECK (status IN ('success', 'escalated', 'failed'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_execution_logs');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 017 (US5, FR-031) — auditoria de cada invocação de ferramenta da IA.
 *
 * Inputs/resultados minimizados e pseudonimizados (sem PII de terceiros nem dado
 * clínico). Correlaciona com ai_execution_logs pelo correlation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tool_invocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('messaging_conversations')->cascadeOnDelete();
            $table->string('correlation_id', 64)->nullable();
            $table->string('tool_name', 64);
            $table->jsonb('input_summary')->nullable();
            $table->string('outcome', 16);
            $table->jsonb('result_summary')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'conversation_id', 'created_at'], 'ai_tool_invocations_tenant_conv_idx');
            $table->index('correlation_id', 'ai_tool_invocations_correlation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_invocations');
    }
};

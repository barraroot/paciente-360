<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 017 (US1/US3) — resumo rolante compacto por conversa.
 *
 * Guarda os fatos-chave dos turnos ANTERIORES à janela verbatim, para que a IA
 * mantenha o fio sem reenviar todas as mensagens (economia de tokens). Sem PII
 * bruta: gerado a partir de mensagens já pseudonimizadas (Princípios I/III).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('messaging_conversations')->cascadeOnDelete();
            $table->text('summary_text')->nullable();
            $table->jsonb('key_facts')->nullable();
            $table->string('funnel_stage', 20)->nullable();
            $table->unsignedBigInteger('covered_up_to_message_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('conversation_id', 'ai_conversation_summaries_conversation_unique');
            $table->index(['tenant_id', 'conversation_id'], 'ai_conversation_summaries_tenant_conv_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_summaries');
    }
};

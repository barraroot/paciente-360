<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 017 (US2) — "Contexto de Trabalho" por clínica.
 *
 * Um registro por tenant (singleton). Híbrido: campos estruturados (serviços,
 * preços, locais, política de sinal, tom, perguntas de qualificação) + texto
 * livre (diferenciais/abordagem). Versionado para auditoria (FR-025). Apenas
 * conteúdo comercial/operacional NÃO-clínico (allow-list no Form Request).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_work_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->jsonb('services')->nullable();
            $table->jsonb('pricing')->nullable();
            $table->jsonb('locations')->nullable();
            $table->jsonb('deposit_policy')->nullable();
            $table->string('tone', 120)->nullable();
            $table->jsonb('qualification_questions')->nullable();
            $table->text('free_form')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id', 'ai_work_contexts_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_work_contexts');
    }
};

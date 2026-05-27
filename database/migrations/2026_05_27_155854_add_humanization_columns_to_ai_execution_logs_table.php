<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 017 — auditoria de qual contexto informou a resposta (FR-024/FR-025):
 * versão do work context, versão do resumo, ferramentas usadas e nº de
 * round-trips de tool na resposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_execution_logs', function (Blueprint $table): void {
            $table->unsignedInteger('work_context_version')->nullable()->after('context_summary');
            $table->unsignedInteger('summary_version')->nullable()->after('work_context_version');
            $table->jsonb('tools_used')->nullable()->after('summary_version');
            $table->unsignedSmallInteger('tool_round_trips')->nullable()->after('tools_used');
        });
    }

    public function down(): void
    {
        Schema::table('ai_execution_logs', function (Blueprint $table): void {
            $table->dropColumn(['work_context_version', 'summary_version', 'tools_used', 'tool_round_trips']);
        });
    }
};

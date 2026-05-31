<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T018 (Fase 18 — US4 + US6)** — colunas de transcrição e sandbox em
 * messaging_messages.
 *
 * - `transcription_id` aponta para audio_transcriptions (NULL em msgs texto-nato);
 * - `is_audio_origin` marca que o conteúdo da mensagem veio de um áudio
 *   transcrito (UI mostra ícone "áudio transcrito");
 * - `sandbox` + `sandbox_session_id` permitem filtrar mensagens de teste das
 *   métricas de produção (FR-042). Agregadores existentes (DashboardHomeService,
 *   ExecutiveDashboard, AiUsageMetrics) precisam ganhar WHERE sandbox=false
 *   antes do release de US6 (T186).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_messages', function (Blueprint $table): void {
            $table->foreignId('transcription_id')
                ->nullable()
                ->after('id')
                ->constrained('audio_transcriptions')
                ->nullOnDelete();

            $table->boolean('is_audio_origin')->default(false)->after('transcription_id');

            $table->boolean('sandbox')->default(false)->after('is_audio_origin');

            $table->foreignUuid('sandbox_session_id')
                ->nullable()
                ->after('sandbox')
                ->constrained('persona_test_sessions')
                ->cascadeOnDelete();
        });

        // Excluir sandbox de agregadores de produção.
        DB::statement('CREATE INDEX messaging_messages_tenant_sandbox_idx ON messaging_messages (tenant_id, sandbox)');
        // Sessão sandbox → mensagens.
        DB::statement('CREATE INDEX messaging_messages_sandbox_session_idx ON messaging_messages (sandbox_session_id) WHERE sandbox_session_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('messaging_messages', function (Blueprint $table): void {
            $table->dropForeign(['sandbox_session_id']);
            $table->dropForeign(['transcription_id']);
            $table->dropColumn(['transcription_id', 'is_audio_origin', 'sandbox', 'sandbox_session_id']);
        });
    }
};

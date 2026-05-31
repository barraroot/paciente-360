<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T014 (Fase 18 — US4)** — transcrição de cada áudio inbound (STT).
 *
 * Uma linha por áudio processado. `transcribed_text` é o texto bruto vindo
 * do provedor (Whisper); ANTES de ir ao modelo de IA passa por PiiScrubber
 * (Fase 17 — FR-055b).
 *
 * Retenção: alinhada com messaging_message_media (Fase 13). NÃO depende do
 * consent `Transcricao` — é texto, sem voz biométrica. O ÁUDIO BRUTO
 * (referenciado por media_id) é o que requer consent Transcricao para
 * retenção prolongada (FR-055a — PurgeExpiredAudioRawJob T210).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_transcriptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('messaging_messages')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('messaging_message_media')->cascadeOnDelete();

            $table->string('provider', 40); // openai_whisper | google_stt | azure_speech
            $table->string('language_detected', 10)->nullable(); // pt-BR | pt | en | es

            $table->text('transcribed_text')->nullable();

            $table->boolean('truncated')->default(false);

            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->timestampsTz();
        });

        // Uma transcrição por mensagem.
        DB::statement('CREATE UNIQUE INDEX audio_transcriptions_message_unique ON audio_transcriptions (tenant_id, message_id)');
        // Queries de métrica.
        DB::statement('CREATE INDEX audio_transcriptions_tenant_created_idx ON audio_transcriptions (tenant_id, created_at)');
        // Análise de falhas por provedor.
        DB::statement('CREATE INDEX audio_transcriptions_provider_error_idx ON audio_transcriptions (provider, error_code) WHERE error_code IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_transcriptions');
    }
};

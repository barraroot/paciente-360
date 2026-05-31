<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T015 (Fase 18 — US5)** — áudio outbound TTS gerado para cada resposta da
 * IA que dispara o gatilho explícito de áudio (Q3=A — FR-031).
 *
 * `source_text` é o texto que veio do modelo; `normalized_text` é o texto
 * após TtsTextNormalizer (FR-035 — R$/horários/datas para fala).
 *
 * Em caso de falha, `fallback_to_text=true` indica que a resposta foi
 * enviada como texto sem perda (FR-034); `error_code` registra a causa.
 *
 * Retenção: igual a messaging_message_media outbound (Fase 13 — FR-056).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_syntheses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('messaging_messages')->cascadeOnDelete();
            $table->foreignId('media_id')
                ->nullable()
                ->constrained('messaging_message_media')
                ->nullOnDelete();
            $table->foreignId('voice_id')->constrained('voice_catalog')->restrictOnDelete();

            $table->string('provider', 40); // elevenlabs | openai_tts | azure_tts

            $table->text('source_text');
            $table->text('normalized_text');

            $table->boolean('segmented')->default(false); // FR-036

            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();

            $table->boolean('fallback_to_text')->default(false); // FR-034

            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX audio_syntheses_message_unique ON audio_syntheses (tenant_id, message_id)');
        DB::statement('CREATE INDEX audio_syntheses_tenant_created_idx ON audio_syntheses (tenant_id, created_at)');
        DB::statement('CREATE INDEX audio_syntheses_provider_error_idx ON audio_syntheses (provider, error_code) WHERE error_code IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_syntheses');
    }
};

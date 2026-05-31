<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **T021 (Fase 18 — Q-clarify-4=B + decisão T021)** — default_voice_id +
 * tts_enabled diretamente na tabela `tenants`.
 *
 * Decisão tomada após inspeção do schema (T021 do tasks.md): NÃO existe
 * `tenant_settings` nem coluna prévia em `tenants`; logo, ADD COLUMN
 * diretamente (caminho mais simples; preserva padrão da plataforma).
 *
 * - `default_voice_id` — fallback de voz para Personas sem voice_id
 *   explícito (FR-037a — cadeia Persona.voice_id → tenant.default_voice_id
 *   → system default);
 * - `tts_enabled` — kill switch do TTS por tenant (FR-037). Quando false,
 *   a IA responde sempre em texto e registra a decisão (não falha).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('default_voice_id')
                ->nullable()
                ->constrained('voice_catalog')
                ->nullOnDelete();

            $table->boolean('tts_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropForeign(['default_voice_id']);
            $table->dropColumn(['default_voice_id', 'tts_enabled']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T010 (Fase 18 — US5, Q-clarify-4=B)** — catálogo global de vozes TTS.
 *
 * NÃO é tenant-scoped — é gerenciado pelo super-admin via Filament
 * (T160). O resolvedor PersonaVoiceResolverService usa a cadeia
 * Persona.voice_id → tenant.default_voice_id → system_default desta tabela.
 *
 * `provider_voice_id` NÃO é exposto ao admin de clínica (FR-037c — sem
 * identificador técnico no painel tenant); o admin escolhe pelo
 * `display_name` + gênero/tom declarados.
 *
 * UNIQUE parcial garante exatamente 1 voz com `is_system_default=true`
 * por language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_catalog', function (Blueprint $table): void {
            $table->id();

            $table->string('provider', 40);
            $table->string('provider_voice_id', 80);

            $table->string('display_name', 80);
            $table->string('gender', 10); // f|m|neutral
            $table->string('tone', 40);   // acolhedor|profissional|energico|calmo
            $table->string('language', 10)->default('pt-BR');

            $table->string('preview_audio_path', 255)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_default')->default(false);

            $table->timestampsTz();
        });

        // Identificador técnico único por provedor.
        DB::statement('CREATE UNIQUE INDEX voice_catalog_provider_voice_unique ON voice_catalog (provider, provider_voice_id)');

        // Listagem da UI (admin de clínica).
        DB::statement('CREATE INDEX voice_catalog_language_active_idx ON voice_catalog (language, is_active)');

        // Apenas UMA voz com is_system_default=true por language.
        DB::statement('CREATE UNIQUE INDEX voice_catalog_system_default_per_language_unique ON voice_catalog (language) WHERE is_system_default = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_catalog');
    }
};

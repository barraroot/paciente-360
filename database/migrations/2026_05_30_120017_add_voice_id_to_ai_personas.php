<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T017 (Fase 18 — Q-clarify-4=B)** — voice_id como atributo da Persona.
 *
 * Cada Persona tem `voice_id` configurável (FR-037a). PersonaVoiceResolver
 * (T146) usa cadeia Persona.voice_id → tenant.default_voice_id → system_default.
 *
 * NULL em existing Personas — fallback resolve normalmente. ON DELETE SET NULL
 * permite super-admin desativar/remover voz sem quebrar Personas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_personas', function (Blueprint $table): void {
            $table->foreignId('voice_id')
                ->nullable()
                ->after('default_model')
                ->constrained('voice_catalog')
                ->nullOnDelete();
        });

        DB::statement('CREATE INDEX ai_personas_voice_id_idx ON ai_personas (voice_id) WHERE voice_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('ai_personas', function (Blueprint $table): void {
            $table->dropForeign(['voice_id']);
            $table->dropColumn('voice_id');
        });
    }
};

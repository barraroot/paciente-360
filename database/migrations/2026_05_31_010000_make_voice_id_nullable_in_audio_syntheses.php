<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **T147-aux (Fase 18 — US5, FR-034)** — torna `voice_id` nullable em
 * `audio_syntheses` para suportar o caso de fallback IMEDIATO quando
 * o resolver não encontra nenhuma voz aplicável (Persona/tenant/system
 * todos NULL). O caller cai para envio texto-only (FR-034) e este
 * registro fica apenas como audit do fallback.
 *
 * Quando `voice_id IS NULL`, `fallback_to_text` é sempre `true`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE audio_syntheses ALTER COLUMN voice_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audio_syntheses ALTER COLUMN voice_id SET NOT NULL');
    }
};

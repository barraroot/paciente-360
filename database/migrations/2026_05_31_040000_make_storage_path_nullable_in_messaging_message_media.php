<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **Fase 18 — Polish T210** — `PurgeExpiredAudioRawJob` precisa zerar
 * `storage_path` quando deleta o arquivo físico do disk, sinalizando que
 * a mídia foi purgada (junto com `media_purged_at`). A coluna foi
 * declarada NOT NULL na Fase 3 (assumindo que toda linha sempre apontava
 * para um arquivo). Agora pode ser nula após purge LGPD-aware.
 *
 * Aditiva, idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE messaging_message_media ALTER COLUMN storage_path DROP NOT NULL');
    }

    public function down(): void
    {
        // Não revertemos — tornar NOT NULL com linhas nulas existentes quebraria.
    }
};

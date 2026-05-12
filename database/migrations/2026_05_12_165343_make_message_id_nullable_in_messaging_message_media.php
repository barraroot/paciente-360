<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T249 — Torna `message_id` nullable em `messaging_message_media`.
 *
 * Uploads pré-assinados criam um registro placeholder com `message_id = NULL`
 * enquanto aguardam ser vinculados via `MediaUploadService::attachToMessage`.
 * A FK é mantida; SET NULL seria ideal mas usamos nullable + check manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Primeiro dropa a FK existente (que exige NOT NULL implicitamente)
        DB::statement('ALTER TABLE messaging_message_media DROP CONSTRAINT IF EXISTS messaging_message_media_message_id_foreign');

        Schema::table('messaging_message_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('message_id')->nullable()->change();
        });

        // Re-adiciona FK com SET NULL para facilitar limpeza de uploads abandonados
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_message_media
            ADD CONSTRAINT messaging_message_media_message_id_foreign
            FOREIGN KEY (message_id) REFERENCES messaging_messages(id) ON DELETE SET NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE messaging_message_media DROP CONSTRAINT IF EXISTS messaging_message_media_message_id_foreign');

        // Deleta registros sem message_id antes de tornar NOT NULL novamente
        DB::statement('DELETE FROM messaging_message_media WHERE message_id IS NULL');

        Schema::table('messaging_message_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('message_id')->nullable(false)->change();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_message_media
            ADD CONSTRAINT messaging_message_media_message_id_foreign
            FOREIGN KEY (message_id) REFERENCES messaging_messages(id) ON DELETE CASCADE
        SQL);
    }
};

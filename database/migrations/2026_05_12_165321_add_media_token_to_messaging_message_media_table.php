<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T249 — Adiciona colunas de upload pré-assinado à tabela `messaging_message_media`.
 *
 * `media_token`: token de 64 chars hex (bin2hex(random_bytes(32))) que identifica um
 * upload pendente. Gerado em `MediaUploadService::requestUpload`, consumido em
 * `attachToMessage`. Nulo após vinculação.
 *
 * `media_token_expires_at`: TTL do token (1h). Após expirar, o upload é ignorado
 * e o registro pode ser limpo.
 *
 * Partial index filtra apenas uploads pendentes (token não nulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_message_media', function (Blueprint $table): void {
            $table->string('media_token', 64)->nullable()->after('media_purged_at');
            $table->timestampTz('media_token_expires_at')->nullable()->after('media_token');
        });

        // Lookup rápido por token durante attachToMessage
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_message_media_token_idx
            ON messaging_message_media (tenant_id, media_token)
            WHERE media_token IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messaging_message_media_token_idx');

        Schema::table('messaging_message_media', function (Blueprint $table): void {
            $table->dropColumn(['media_token', 'media_token_expires_at']);
        });
    }
};

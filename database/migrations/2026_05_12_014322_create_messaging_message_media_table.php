<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T016 — Cria a tabela `messaging_message_media`.
 *
 * Metadados de mídia anexada a mensagens. O arquivo real fica em S3 (disk `media`).
 *
 * `sensitive_hint` default true por precaução LGPD — admin pode reverter manualmente.
 * `media_purged_at` preenchido pelo job de retenção (1 ano) ao deletar do S3.
 * Partial index em `media_purged_at IS NULL` cobre exatamente o subset purgável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_message_media', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('message_id');
            $table->foreign('message_id')->references('id')->on('messaging_messages')->onDelete('cascade');

            $table->string('storage_disk', 20)->default('media');
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('original_filename', 255)->nullable();
            $table->char('checksum_sha256', 64);
            $table->boolean('sensitive_hint')->default(true);
            $table->timestampTz('media_purged_at')->nullable();

            $table->timestampsTz();
        });

        // Listar mídia de uma mensagem
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_message_media_tenant_message_idx
            ON messaging_message_media (tenant_id, message_id)
        SQL);

        // Job de purge mensal: só varre mídia ainda não purgada
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_message_media_purge_idx
            ON messaging_message_media (tenant_id, created_at)
            WHERE media_purged_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_message_media');
    }
};

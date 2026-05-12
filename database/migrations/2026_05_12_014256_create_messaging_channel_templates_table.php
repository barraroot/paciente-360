<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T012 — Cria a tabela `messaging_channel_templates`.
 *
 * Templates aprovados pela Meta/Twilio. No MVP o tenant apenas consome
 * (sync read-only via Content API ou Graph API) — não cadastra manualmente.
 *
 * UNIQUE (channel_id, provider_template_id) garante idempotência no sync periódico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_channel_templates', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('channel_id');
            $table->foreign('channel_id')->references('id')->on('messaging_channels')->onDelete('cascade');

            $table->string('provider_template_id', 100);
            $table->string('meta_template_name', 100);
            $table->string('meta_template_status', 20);
            $table->string('language', 10)->default('pt_BR');
            $table->string('category', 30)->nullable();
            $table->text('body_preview')->nullable();
            $table->jsonb('variables_schema')->default('[]');
            $table->timestampTz('last_synced_at');

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_channel_templates
            ADD CONSTRAINT messaging_channel_templates_status_check
            CHECK (meta_template_status IN ('approved', 'pending', 'rejected', 'paused'))
        SQL);

        // Sync idempotente: um provider_template_id por canal
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_channel_templates_channel_provider_unique
            ON messaging_channel_templates (channel_id, provider_template_id)
        SQL);

        // Seletor de templates na UI: filtrado por status approved do canal X do tenant Y
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_channel_templates_tenant_channel_status_idx
            ON messaging_channel_templates (tenant_id, channel_id, meta_template_status)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_channel_templates');
    }
};

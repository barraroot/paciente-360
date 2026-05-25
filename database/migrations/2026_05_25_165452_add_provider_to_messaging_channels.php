<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 014 — Dimensão de provedor no canal de WhatsApp.
 *
 * `provider` (twilio|evolution), default 'twilio' (retrocompatível). UNIQUE parcial
 * garante um único canal WhatsApp ativo/conectando por tenant (R7).
 *
 * @see specs/014-channel-provider-integration/data-model.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_channels', function (Blueprint $table): void {
            $table->string('provider', 20)->default('twilio')->after('type');
            $table->index(['tenant_id', 'type', 'provider'], 'messaging_channels_tenant_type_provider_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_channels
            ADD CONSTRAINT chk_messaging_channels_provider
            CHECK (provider IN ('twilio','evolution'))
        SQL);

        // Adiciona 'conectando' ao CHECK de status (pareamento Evolution em curso).
        DB::statement('ALTER TABLE messaging_channels DROP CONSTRAINT IF EXISTS messaging_channels_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_channels
            ADD CONSTRAINT messaging_channels_status_check
            CHECK (status IN ('ativo','conectando','desconectado','invalido','expirado','degradado','suspenso'))
        SQL);

        // Adiciona 'evolution' ao CHECK de provider dos webhook events (inbound não oficial).
        DB::statement('ALTER TABLE messaging_webhook_events DROP CONSTRAINT IF EXISTS messaging_webhook_events_provider_check');
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_webhook_events
            ADD CONSTRAINT messaging_webhook_events_provider_check
            CHECK (provider IN ('twilio','meta','widget','evolution'))
        SQL);

        // Um WhatsApp ativo/conectando por tenant (independente do provedor).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX one_active_whatsapp_per_tenant
            ON messaging_channels (tenant_id)
            WHERE type = 'whatsapp' AND status IN ('ativo','conectando') AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS one_active_whatsapp_per_tenant');
        DB::statement('ALTER TABLE messaging_channels DROP CONSTRAINT IF EXISTS chk_messaging_channels_provider');

        // Restaura o CHECK de status original (sem 'conectando').
        DB::statement('ALTER TABLE messaging_channels DROP CONSTRAINT IF EXISTS messaging_channels_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_channels
            ADD CONSTRAINT messaging_channels_status_check
            CHECK (status IN ('ativo','desconectado','invalido','expirado','degradado','suspenso'))
        SQL);

        // Restaura o CHECK de provider dos webhook events (sem 'evolution').
        DB::statement('ALTER TABLE messaging_webhook_events DROP CONSTRAINT IF EXISTS messaging_webhook_events_provider_check');
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_webhook_events
            ADD CONSTRAINT messaging_webhook_events_provider_check
            CHECK (provider IN ('twilio','meta','widget'))
        SQL);

        Schema::table('messaging_channels', function (Blueprint $table): void {
            $table->dropIndex('messaging_channels_tenant_type_provider_idx');
            $table->dropColumn('provider');
        });
    }
};

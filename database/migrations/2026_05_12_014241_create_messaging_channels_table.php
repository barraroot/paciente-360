<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T011 — Cria a tabela `messaging_channels`.
 *
 * Canal externo conectado ao tenant (WhatsApp/Twilio, Instagram/Meta, Web Widget).
 *
 * Pontos críticos:
 *  - UNIQUE parciais com path JSONB (->>) não são suportados pelo Schema Builder — usamos DB::statement.
 *  - CHECK constraints emulam ENUM para extensibilidade futura sem DDL.
 *  - Índice parcial de monitoramento cobre apenas canais em estado degradado/inválido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_channels', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('type', 20);
            $table->string('name', 100);
            $table->string('status', 20)->default('ativo');

            $table->text('credentials_encrypted')->nullable();

            $table->jsonb('provider_metadata')->default('{}');

            $table->string('quality_rating', 20)->nullable();
            $table->timestampTz('quality_rating_updated_at')->nullable();
            $table->timestampTz('last_health_check_at')->nullable();
            $table->boolean('auto_send_disabled')->default(false);

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // CHECK constraints
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_channels
            ADD CONSTRAINT messaging_channels_type_check
            CHECK (type IN ('whatsapp', 'instagram', 'web'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_channels
            ADD CONSTRAINT messaging_channels_status_check
            CHECK (status IN ('ativo', 'desconectado', 'invalido', 'expirado', 'degradado', 'suspenso'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_channels
            ADD CONSTRAINT messaging_channels_quality_rating_check
            CHECK (quality_rating IS NULL OR quality_rating IN ('high', 'medium', 'low', 'flagged'))
        SQL);

        // UNIQUE parcial: 1 número WhatsApp por tenant
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_channels_whatsapp_phone_unique
            ON messaging_channels (tenant_id, type, (provider_metadata->>'phone_number_id'))
            WHERE type = 'whatsapp'
        SQL);

        // UNIQUE parcial: 1 conta Instagram por tenant
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_channels_instagram_account_unique
            ON messaging_channels (tenant_id, type, (provider_metadata->>'ig_business_account_id'))
            WHERE type = 'instagram'
        SQL);

        // UNIQUE global: chave pública do widget (lookup runtime pelo JS do widget)
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_channels_web_public_key_unique
            ON messaging_channels ((provider_metadata->>'public_key'))
            WHERE type = 'web'
        SQL);

        // Listagem de canais por tenant — query dominante do painel de configurações
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_channels_tenant_type_status_idx
            ON messaging_channels (tenant_id, type, status)
        SQL);

        // Monitoramento global de canais em estado problemático (partial reduz tamanho do índice)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_channels_degraded_status_idx
            ON messaging_channels (status)
            WHERE status IN ('degradado', 'invalido')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_channels');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T018 — Cria a tabela `messaging_web_widget_configs`.
 *
 * Configuração do widget web por tenant. Relação 1:1 com `messaging_channels`
 * quando type='web'. Tabela pequena (1-2 linhas por tenant).
 *
 * `public_key` é globalmente única — usada em runtime pelo JS do widget
 * para identificar qual tenant/config carregar. UNIQUE crítico.
 *
 * `business_hours` JSONB: {monday:"08:00-18:00", ..., timezone:"America/Sao_Paulo"}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_web_widget_configs', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('channel_id');
            $table->foreign('channel_id')->references('id')->on('messaging_channels')->onDelete('cascade');

            $table->string('public_key', 64);
            $table->jsonb('allowed_origins')->default('[]');
            $table->jsonb('appearance')->default('{}');
            $table->text('initial_message')->nullable();
            $table->jsonb('business_hours')->default('{}');
            $table->string('outside_hours_behavior', 20)->default('fila');
            $table->string('pre_chat_form', 30)->default('opcional');
            $table->text('outside_hours_message')->nullable();

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_web_widget_configs
            ADD CONSTRAINT messaging_web_widget_configs_outside_hours_behavior_check
            CHECK (outside_hours_behavior IN ('bloqueia', 'fila', 'normal'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_web_widget_configs
            ADD CONSTRAINT messaging_web_widget_configs_pre_chat_form_check
            CHECK (pre_chat_form IN ('opcional', 'exigido_para_iniciar', 'exigido_para_enviar'))
        SQL);

        // 1:1 com canal web
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_web_widget_configs_channel_unique
            ON messaging_web_widget_configs (channel_id)
        SQL);

        // Lookup runtime pelo JS do widget — chave pública globalmente única
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_web_widget_configs_public_key_unique
            ON messaging_web_widget_configs (public_key)
        SQL);

        // Listagem por tenant (rara — máx. 1-2 widgets por tenant)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_web_widget_configs_tenant_idx
            ON messaging_web_widget_configs (tenant_id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_web_widget_configs');
    }
};

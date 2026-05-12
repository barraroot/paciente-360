<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T019 — Cria a tabela `messaging_web_widget_sessions`.
 *
 * Sessões de visitantes anônimos no widget. Lead provisório que pode virar
 * paciente formal (NC-1.c, NC-10.c).
 *
 * Pontos LGPD:
 *  - `ip_hash` SHA-256 do IP — não armazenamos IP plain.
 *  - `provisional_data` JSONB: nome/telefone do form pré-chat (não persiste em pacientes).
 *  - Sessões de visitantes não-identificados expiram em 30 dias (`expires_at`).
 *
 * `visitor_token` globalmente único: cookie/localStorage do browser do visitante.
 * Partial index em `expires_at WHERE identified_patient_id IS NULL` cobre exatamente
 * o subset purgável (visitantes não-identificados expirados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_web_widget_sessions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('widget_config_id');
            $table->foreign('widget_config_id')->references('id')->on('messaging_web_widget_configs')->onDelete('cascade');

            $table->string('visitor_token', 64);
            $table->char('ip_hash', 64);
            $table->text('user_agent')->nullable();
            $table->string('referer_domain', 255)->nullable();

            $table->timestampTz('started_at')->default(DB::raw('now()'));
            $table->timestampTz('last_activity_at')->default(DB::raw('now()'));

            $table->unsignedBigInteger('identified_patient_id')->nullable();
            $table->foreign('identified_patient_id')->references('id')->on('pacientes')->onDelete('set null');

            $table->jsonb('provisional_data')->default('{}');
            $table->timestampTz('expires_at');

            $table->timestampsTz();
        });

        // Lookup direto pelo token do browser — globalmente único
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_web_widget_sessions_token_unique
            ON messaging_web_widget_sessions (visitor_token)
        SQL);

        // Sessões recentes de um widget — admin visualiza visitantes ativos
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_web_widget_sessions_widget_activity_idx
            ON messaging_web_widget_sessions (tenant_id, widget_config_id, last_activity_at DESC)
        SQL);

        // Purge job mensal: apenas visitantes anônimos expirados
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_web_widget_sessions_purge_idx
            ON messaging_web_widget_sessions (expires_at)
            WHERE identified_patient_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_web_widget_sessions');
    }
};

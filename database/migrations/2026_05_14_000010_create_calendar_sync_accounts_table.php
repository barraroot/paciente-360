<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T015 — `calendar_sync_accounts` (OAuth Google — US-6.7 — clarify nº 10/11/15).
 *
 * Pontos críticos:
 *
 *  1) **UNIQUE(tenant_id, professional_id)** — clarify nº 15: 1 conexão por par.
 *     Profissional em 2 tenants gera 2 rows distintas (sub-calendário diferente em cada).
 *
 *  2) **encrypted_access_token / encrypted_refresh_token**: cast `encrypted` em
 *     Eloquent Model — Crypt::encryptString aplicado antes de persistir
 *     (Princípio I — tokens são credenciais de terceiros, mesmo nível de senha).
 *
 *  3) **`google_calendar_id`** — sub-calendário dedicado `Paciente360 — {Tenant.nome}`
 *     criado automaticamente no fluxo connect (clarify nº 15).
 *
 *  4) **provider enum aceita 'outlook'** — preparado para Fase 6 (clarify nº 11).
 *     Service rejeita outlook na Fase 5 com `provider_not_yet_supported`.
 *
 *  5) **Watch channels**: `watch_channel_id`, `watch_channel_resource_id`,
 *     `watch_channel_expires_at` — push notifications Google (R3).
 *
 *  6) **OAuth UX (R5)**: `last_disconnect_at`, `last_disconnect_notified_at`,
 *     `last_reconnect_at` para banner in-app + email dedup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_sync_accounts', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');

            $table->string('provider', 16);
            $table->string('provider_user_id', 128);
            $table->string('provider_email', 254);

            $table->text('encrypted_access_token');
            $table->text('encrypted_refresh_token')->nullable();

            $table->timestampTz('expires_at')->nullable();

            $table->string('google_calendar_id', 255)->nullable();
            $table->string('google_calendar_name_seen', 255)->nullable();

            $table->uuid('watch_channel_id')->nullable();
            $table->string('watch_channel_resource_id', 255)->nullable();
            $table->timestampTz('watch_channel_expires_at')->nullable();

            $table->timestampTz('last_polled_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();

            $table->timestampTz('last_disconnect_at')->nullable();
            $table->timestampTz('last_disconnect_notified_at')->nullable();
            $table->timestampTz('last_reconnect_at')->nullable();

            $table->string('status', 16)->default('connected');

            $table->timestampsTz();

            // Gate clarify nº 15 — 1 conexão por (tenant × prof)
            $table->unique(['tenant_id', 'professional_id'], 'csa_tenant_prof_unique');

            $table->index(['provider', 'status'], 'csa_provider_status_idx');
        });

        DB::statement("
            ALTER TABLE calendar_sync_accounts
            ADD CONSTRAINT csa_provider_check
            CHECK (provider IN ('google', 'outlook'))
        ");

        DB::statement("
            ALTER TABLE calendar_sync_accounts
            ADD CONSTRAINT csa_status_check
            CHECK (status IN ('connected', 'disconnected', 'error'))
        ");

        // PARTIAL UNIQUE: lookup do webhook por watch_channel_id
        DB::statement('
            CREATE UNIQUE INDEX csa_watch_channel_unique
            ON calendar_sync_accounts (provider, watch_channel_id)
            WHERE watch_channel_id IS NOT NULL
        ');

        // PARTIAL INDEX para cron renew watch channels
        DB::statement("
            CREATE INDEX csa_watch_renew_idx
            ON calendar_sync_accounts (watch_channel_expires_at)
            WHERE status = 'connected' AND watch_channel_expires_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_accounts');
    }
};

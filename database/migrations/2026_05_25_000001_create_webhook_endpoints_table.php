<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **T188 (Fase 8 — Lote D US-11.1)** — Webhook endpoints.
 *
 * Cada tenant configura até `plans.webhook_max_endpoints` URLs ativas.
 * Subscription via `events_subscribed` (jsonb array de nomes Q17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            // HMAC secret (criptografado via cast 'encrypted' no model).
            $table->text('secret');
            $table->jsonb('events_subscribed');
            $table->boolean('is_active')->default(true);
            $table->integer('failure_count')->default(0);
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'is_active']);
            // Lookup rápido por evento subscrito.
            $table->index('events_subscribed', 'idx_webhook_endpoints_events_gin', 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};

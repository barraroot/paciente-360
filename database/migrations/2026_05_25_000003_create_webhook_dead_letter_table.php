<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **T188 (Fase 8 — Lote D US-11.1)** — Dead Letter Queue.
 *
 * Recebe webhook_deliveries após 5 retries esgotados. Retenção 30d via
 * `purge:expired-dlq` (T202). Admin pode reenviar manualmente (AC-11.1.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_dead_letter', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_delivery_id')->constrained('webhook_deliveries')->cascadeOnDelete();
            $table->string('event_type');
            $table->uuid('event_id');
            $table->string('correlation_id', 36);
            $table->jsonb('payload');
            $table->jsonb('failure_history'); // [{attempt, http_code, error, occurred_at}, ...]
            $table->timestampTz('failed_at');
            $table->timestampTz('expires_at'); // now()+30d via service
            $table->foreignId('resent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resent_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'expires_at']);
            $table->index(['webhook_endpoint_id', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_dead_letter');
    }
};

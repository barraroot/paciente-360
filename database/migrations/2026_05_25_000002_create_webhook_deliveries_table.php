<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T188 (Fase 8 — Lote D US-11.1)** — Log de entregas de webhook.
 *
 * Cada tentativa (incluindo retries) escreve uma row. `event_id` UNIQUE por
 * endpoint enforce idempotência (re-emissão do mesmo evento não duplica entrega).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'webhook_delivery_status_enum') THEN
                    CREATE TYPE webhook_delivery_status_enum AS ENUM (
                        'pending', 'delivered', 'failed', 'retrying', 'dead_letter'
                    );
                END IF;
            END$$;
        SQL);

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // ex: 'agendamento.criado' (Q17)
            $table->uuid('event_id'); // ID estável do evento dispatched
            $table->string('correlation_id', 36);
            $table->jsonb('payload');
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->jsonb('last_response')->nullable(); // {http_code, body_snippet, duration_ms}
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['webhook_endpoint_id', 'event_id'], 'uq_webhook_deliveries_endpoint_event');
            $table->index(['tenant_id', 'status']);
            $table->index('next_attempt_at');
        });

        DB::statement('ALTER TABLE webhook_deliveries ALTER COLUMN status TYPE webhook_delivery_status_enum USING status::webhook_delivery_status_enum');
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        DB::statement('DROP TYPE IF EXISTS webhook_delivery_status_enum');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T022 — Cria a tabela `messaging_webhook_events`.
 *
 * Eventos de webhook recebidos de providers externos. Idempotência cross-provider (R9).
 *
 * IMPORTANTE: esta tabela NÃO usa `BelongsToTenant` — `tenant_id` é NULLABLE
 * porque o tenant é resolvido no job de processamento via lookup do `channel_id`.
 * O dedup (gate de idempotência) deve funcionar antes do tenant ser resolvido.
 *
 * Coluna `raw_payload_encrypted` cifrada via cast `encrypted` no Model —
 * payload bruto pode conter PII (número de telefone, conteúdo da mensagem).
 *
 * UNIQUE (provider, external_id) é o coração da idempotência: sem `tenant_id`
 * porque providers podem entregar external_id antes do canal ser resolvido.
 *
 * Retenção: 30 dias (purge job mensal). Audit completo já está em `audit_logs`.
 * Alta volumetria (~1M por tenant, purge curto → ~100k ativos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_webhook_events', function (Blueprint $table): void {
            $table->id();

            $table->string('provider', 20);
            $table->string('external_id', 255);

            $table->unsignedBigInteger('channel_id')->nullable();
            $table->foreign('channel_id')->references('id')->on('messaging_channels')->onDelete('set null');

            // tenant_id NULLABLE — preenchido pós-processamento
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('event_type', 50);
            $table->text('raw_payload_encrypted');
            $table->boolean('signature_verified');
            $table->timestampTz('received_at')->default(DB::raw('now()'));
            $table->string('status', 20)->default('received');

            $table->timestampTz('processing_started_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->text('failure_reason')->nullable();

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_webhook_events
            ADD CONSTRAINT messaging_webhook_events_provider_check
            CHECK (provider IN ('twilio', 'meta', 'widget'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_webhook_events
            ADD CONSTRAINT messaging_webhook_events_status_check
            CHECK (status IN ('received', 'processing', 'processed', 'failed', 'duplicate'))
        SQL);

        // Gate de idempotência: UNIQUE por provider sem tenant — INSERT ON CONFLICT DO NOTHING
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_webhook_events_provider_external_id_unique
            ON messaging_webhook_events (provider, external_id)
        SQL);

        // Monitor de fila e retry: apenas eventos não-finalizados
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_webhook_events_queue_monitor_idx
            ON messaging_webhook_events (status, received_at)
            WHERE status IN ('received', 'processing', 'failed')
        SQL);

        // Auditoria por canal
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_webhook_events_channel_received_idx
            ON messaging_webhook_events (channel_id, received_at DESC)
            WHERE channel_id IS NOT NULL
        SQL);

        // Purge job mensal: índice simples em received_at para scan por range de data.
        // Partial com now() não é suportado em PG (now() não é IMMUTABLE).
        // O job faz: WHERE received_at < now() - interval '30 days' usando este índice.
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_webhook_events_purge_idx
            ON messaging_webhook_events (received_at)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_webhook_events');
    }
};

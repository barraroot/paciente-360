<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T015 — Cria a tabela `messaging_messages`.
 *
 * Entidade de maior volumetria (até 500k por tenant). Append-only do ponto de
 * vista de domínio — apenas campos de status (delivered_at, read_at) são atualizados.
 *
 * Pontos críticos LGPD:
 *  - `body` TEXT: cifrado via cast `encrypted` (AES-256-CBC com APP_KEY) no Model.
 *    Coluna TEXT permite Laravel encrypt/decrypt sem binário.
 *  - `body_searchable` VARCHAR(2000): plain text para indexação trigram.
 *    Nunca aparece em logs — mesma retenção que `body`.
 *  - `body_searchable_normalized` GENERATED ALWAYS AS STORED:
 *    lower(immutable_unaccent(coalesce(body_searchable, ''))) — não indexa `body` cifrado.
 *  - `body_preview` VARCHAR(140): primeiros 140 chars para inbox listing sem decriptar.
 *
 * `sender_id` é FK ambígua (users.id ou pacientes.id dependendo de sender_type) —
 * sem FK no banco por design (polimorfismo). Validado na camada de domínio.
 *
 * Índice GIN trigram e BRIN em created_at ficam em T023 (migration separada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_messages', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('conversation_id');
            $table->foreign('conversation_id')->references('id')->on('messaging_conversations')->onDelete('cascade');

            $table->string('direction', 3);
            $table->string('sender_type', 20);

            // FK ambígua: users.id ou pacientes.id — resolvida no Model, sem FK no banco
            $table->unsignedBigInteger('sender_id')->nullable();

            // Corpo cifrado + variantes para search e preview (LGPD)
            $table->text('body')->nullable();
            $table->string('body_searchable', 2000)->nullable();
            // body_searchable_normalized: GENERATED — adicionada abaixo via ALTER TABLE
            $table->string('body_preview', 140)->nullable();

            $table->string('content_type', 20);
            $table->string('template_provider_id', 100)->nullable();
            $table->jsonb('template_variables')->nullable();

            $table->string('external_id', 255)->nullable();
            $table->jsonb('external_metadata')->default('{}');

            $table->string('status', 20)->default('queued');
            $table->string('failure_reason', 255)->nullable();
            $table->string('idempotency_key', 64)->nullable();

            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();

            $table->timestampsTz();
        });

        // Coluna GENERATED ALWAYS AS STORED para busca trigram normalizada
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_messages
            ADD COLUMN body_searchable_normalized VARCHAR(2000)
            GENERATED ALWAYS AS (lower(immutable_unaccent(coalesce(body_searchable, '')))) STORED
        SQL);

        // CHECK constraints
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_messages
            ADD CONSTRAINT messaging_messages_direction_check
            CHECK (direction IN ('in', 'out'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_messages
            ADD CONSTRAINT messaging_messages_sender_type_check
            CHECK (sender_type IN ('patient', 'user', 'system', 'ai'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_messages
            ADD CONSTRAINT messaging_messages_content_type_check
            CHECK (content_type IN ('text', 'image', 'audio', 'video', 'document', 'template', 'interactive', 'location', 'contact'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_messages
            ADD CONSTRAINT messaging_messages_status_check
            CHECK (status IN ('queued', 'sent', 'delivered', 'read', 'failed'))
        SQL);

        // Idempotência cross-provider: external_id único por tenant quando não-nulo
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_messages_tenant_external_id_unique
            ON messaging_messages (tenant_id, external_id)
            WHERE external_id IS NOT NULL
        SQL);

        // Idempotência outbound: idempotency_key globalmente única quando não-nula
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_messages_idempotency_key_unique
            ON messaging_messages (idempotency_key)
            WHERE idempotency_key IS NOT NULL
        SQL);

        // Histórico de uma conversa (query dominante — 95% das queries de mensagens)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_messages_tenant_conv_created_idx
            ON messaging_messages (tenant_id, conversation_id, created_at DESC)
        SQL);

        // Fila de envios pendentes (partial: apenas queued — minoria das linhas)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_messages_tenant_queued_idx
            ON messaging_messages (tenant_id, status)
            WHERE status = 'queued'
        SQL);

        // Monitoring/retry de mensagens com falha
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_messages_tenant_failed_idx
            ON messaging_messages (tenant_id, status)
            WHERE status = 'failed'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T021 — Cria a tabela `messaging_user_presence`.
 *
 * Presença online de atendentes (NC-6.b). 1 registro por user por tenant.
 *
 * `status` é inferido por última atividade (heartbeat WebSocket/HTTP) — sem
 * status manual no MVP. Job periódico atualiza para `offline` quando
 * `last_seen_at` > `auto_assign.user_idle_minutes`.
 *
 * `current_assigned_count` denormalizado evita COUNT() em cada auto-atribuição.
 * Recalculado periodicamente pelo job de consistência.
 *
 * Partial index em `status = 'online'` é hot path da auto-atribuição:
 * "atendentes online com vagas disponíveis".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_user_presence', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('status', 20)->default('offline');
            $table->timestampTz('last_seen_at')->default(DB::raw('now()'));
            $table->integer('max_concurrent_conversations')->default(15);
            $table->integer('current_assigned_count')->default(0);
            $table->jsonb('notification_preferences')->default('{}');

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_user_presence
            ADD CONSTRAINT messaging_user_presence_status_check
            CHECK (status IN ('online', 'away', 'offline'))
        SQL);

        // 1 registro por user por tenant
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_user_presence_tenant_user_unique
            ON messaging_user_presence (tenant_id, user_id)
        SQL);

        // Hot path da auto-atribuição: atendentes online com vaga disponível
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_user_presence_tenant_online_idx
            ON messaging_user_presence (tenant_id, status, last_seen_at)
            WHERE status = 'online'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_user_presence');
    }
};

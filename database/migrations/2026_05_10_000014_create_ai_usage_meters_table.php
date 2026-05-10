<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `ai_usage_meters` — contador mensal de uso de IA por tenant.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 10.
 *
 * Notas:
 *  - `year_month` CHAR(7) no formato `YYYY-MM` (ex.: `2026-05`) funciona
 *    como time bucket. Comparações lexicográficas são equivalentes a
 *    comparações cronológicas com esse formato.
 *  - UNIQUE `(tenant_id, year_month)`: um único registro por tenant por mês.
 *    Atualizações atômicas via `UPDATE ... SET messages_count = messages_count + 1`
 *    (SELECT FOR UPDATE no Service para evitar race condition).
 *  - `included_quota_snapshot`: cópia da cota inclusa no momento da abertura
 *    do bucket. Garante que mudanças de plano mid-month não alteram o
 *    cálculo de overage retroativamente.
 *  - `hard_cap`: NULL = sem cap rígido. Quando atingido, `HardCapService`
 *    ativa modo template-only ou escalona a conversa (FR-019).
 *  - Nesta fase a tabela é criada vazia; listeners e jobs que a populam
 *    vêm em fases futuras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_meters', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->char('year_month', 7);

            $table->integer('messages_count')->default(0);
            $table->integer('included_quota_snapshot');
            $table->integer('overage_count')->default(0);

            $table->integer('hard_cap')->nullable();
            $table->timestampTz('hard_cap_triggered_at')->nullable();

            $table->timestampTz('last_reset_at');

            $table->timestampsTz();
        });

        // Garante um único bucket por tenant por mês.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ai_usage_tenant_month_uniq
            ON ai_usage_meters (tenant_id, year_month)
        SQL);

        // Índice para painel de monitoramento de cap atingido (FR-019).
        DB::statement(<<<'SQL'
            CREATE INDEX ai_usage_hard_cap_triggered_idx
            ON ai_usage_meters (hard_cap_triggered_at)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_meters');
    }
};

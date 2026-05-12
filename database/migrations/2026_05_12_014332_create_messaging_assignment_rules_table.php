<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T020 — Cria a tabela `messaging_assignment_rules`.
 *
 * Regras de auto-atribuição configuráveis por tenant (NC-6.a).
 * MVP suporta `round_robin` e `patient_owner` com fallback round-robin.
 *
 * `channel_id` NULL = regra aplica a todos os canais do tenant.
 * `priority` INT: menor valor = maior prioridade (regras avaliadas em ordem ASC).
 *
 * `config` JSONB permite extensão futura de configurações específicas
 * por estratégia sem nova migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_assignment_rules', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('channel_id')->nullable();
            $table->foreign('channel_id')->references('id')->on('messaging_channels')->onDelete('cascade');

            $table->string('strategy', 30);
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->jsonb('config')->default('{}');

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE messaging_assignment_rules
            ADD CONSTRAINT messaging_assignment_rules_strategy_check
            CHECK (strategy IN ('round_robin', 'patient_owner', 'manual'))
        SQL);

        // Query principal: regras ativas do tenant ordenadas por prioridade
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_assignment_rules_tenant_active_priority_idx
            ON messaging_assignment_rules (tenant_id, is_active, priority)
        SQL);

        // Regras específicas por canal (partial: exclui regras globais)
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_assignment_rules_channel_idx
            ON messaging_assignment_rules (channel_id)
            WHERE channel_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_assignment_rules');
    }
};

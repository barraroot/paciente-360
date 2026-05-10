<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `plans` — catálogo comercial global (sem tenant_id).
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 2.
 *
 * Notas:
 *  - Tabela global: não carrega `tenant_id`. Planos existentes são honrados
 *    via `subscriptions.plan_snapshot` (JSONB) — alterações futuras não
 *    retroagem para contratos vigentes.
 *  - FK de `tenants.plan_id` é adicionada aqui via `DB::statement` (após
 *    a tabela existir) para manter estilo declarativo idêntico ao usado
 *    em `create_tenants_table.php` e evitar `Schema::table` separado.
 *  - Preços em centavos (BIGINT / INTEGER) — sem ponto flutuante (decisão
 *    de convenção do data-model).
 *  - Planos descontinuados ficam `is_active = false`; sem soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();

            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->bigInteger('base_price_cents');
            $table->integer('included_professionals')->default(0);
            $table->integer('included_ai_messages');
            $table->integer('overage_price_cents');

            $table->integer('max_users');
            $table->integer('max_channels');

            $table->string('stripe_price_id_base', 100);
            $table->string('stripe_price_id_overage', 100);

            $table->boolean('is_active')->default(true)->index();

            $table->timestampsTz();
        });

        // Adiciona FK de tenants.plan_id → plans.id agora que plans existe.
        // Usa DB::statement para manter estilo declarativo (sem Schema::table
        // com Blueprint) conforme convenção do projeto.
        DB::statement(<<<'SQL'
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_plan_id_foreign
            FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_plan_id_foreign');
        Schema::dropIfExists('plans');
    }
};

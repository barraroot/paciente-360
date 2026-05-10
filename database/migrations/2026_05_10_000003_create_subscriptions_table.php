<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `subscriptions` — compatível com Laravel Cashier 16.x.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 3.1.
 *
 * Notas:
 *  - Cashier por padrão usa `user_id` como coluna billable. Aqui usamos
 *    `tenant_id` porque o Billable é o Tenant (não o User). O trait
 *    `Billable` será aplicado no Model `Tenant`.
 *  - `plan_snapshot` (JSONB) persiste cópia dos campos relevantes do plan
 *    no momento da contratação, blindando contra edições retroativas no
 *    catálogo global.
 *  - `professionals_quantity` reflete `quantity` do subscription_item base
 *    e é o insumo para cobrança proporcional.
 *  - Campos Cashier: `stripe_id`, `stripe_status`, `stripe_price`,
 *    `quantity`, `trial_ends_at`, `ends_at` mantidos com nomes canônicos
 *    para compatibilidade com métodos do pacote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');

            $table->string('type', 50)->default('default');

            $table->string('stripe_id', 255)->unique();
            $table->string('stripe_status', 20);
            $table->string('stripe_price', 100)->nullable();
            $table->integer('quantity')->nullable();

            $table->unsignedBigInteger('plan_id');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('restrict');

            $table->jsonb('plan_snapshot');
            $table->integer('professionals_quantity')->default(0);

            $table->timestampTz('current_period_start');
            $table->timestampTz('current_period_end');
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

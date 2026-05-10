<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `subscription_items` — schema padrão Laravel Cashier 16.x.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 3.2.
 *
 * Notas:
 *  - Sem custom columns: estrutura 100% padrão Cashier para não comprometer
 *    a compatibilidade com métodos internos do pacote.
 *  - FK para `subscriptions` com ON DELETE CASCADE: ao cancelar/deletar
 *    uma subscription, os itens são removidos automaticamente (dados
 *    descartáveis — não carregam histórico legal).
 *  - Itens esperados por subscription: `base` (recorrente, quantity =
 *    professionals_quantity) e `ai-overage` (metered, quantity NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('subscription_id')->index();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');

            $table->string('stripe_id', 255)->unique();
            $table->string('stripe_product', 100)->nullable();
            $table->string('stripe_price', 100);
            $table->integer('quantity')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};

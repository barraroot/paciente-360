<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `professionals` — esqueleto para fase 4 (agenda/atendimentos).
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 6.
 *
 * Notas:
 *  - Nesta fase entrega APENAS a estrutura da tabela. Toda a funcionalidade
 *    de agenda, horários e atendimentos vem na fase 4.
 *  - `user_id` é NULLABLE: permite cadastrar profissional sem login (ex.:
 *    médico que não acessa o sistema diretamente, apenas agenda).
 *  - FK `user_id` → `users.id` ON DELETE SET NULL: se o usuário for
 *    desativado/deletado, o registro do profissional é mantido (histórico
 *    clínico não pode ser apagado — Princípio I).
 *  - `is_active` é o gate de cobrança: `professionals_quantity` em
 *    `subscriptions` é `COUNT(*) WHERE is_active = true`.
 *  - Índice composto `(tenant_id, is_active)` otimiza a query de contagem
 *    para sincronização com Stripe (chamada frequente em webhooks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->string('name', 150);
            $table->string('council_type', 10)->nullable();
            $table->string('council_number', 20)->nullable();
            $table->char('council_state', 2)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Índice composto principal: query de contagem de profissionais
        // ativos por tenant (gate de cobrança + billing sync com Stripe).
        DB::statement(<<<'SQL'
            CREATE INDEX professionals_tenant_id_active_idx
            ON professionals (tenant_id, is_active)
        SQL);

        // Índice em user_id para lookup reverso (dado um User, achar o
        // Professional correspondente).
        DB::statement(<<<'SQL'
            CREATE INDEX professionals_user_id_idx ON professionals (user_id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};

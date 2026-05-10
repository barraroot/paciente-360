<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `tenants` — raiz do multi-tenancy (Princípio II).
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 1.
 *
 * Notas:
 *  - `status` é VARCHAR(20) com CHECK constraint emulando ENUM
 *    (`trial|active|overdue|suspended|cancelled`), mantendo flexibilidade
 *    para acrescentar valores via nova migration sem `ALTER TYPE`.
 *  - `onboarding_state` é JSONB (operadores e índices nativos do PG18).
 *  - `plan_id` fica sem FK aqui; T021 cria `plans` e adiciona o
 *    `ALTER TABLE` com a constraint (ON DELETE RESTRICT).
 *  - Soft delete (`deleted_at`) — tenants nunca são deletados fisicamente
 *    nesta fase (Princípio I — auditabilidade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();

            $table->string('slug', 63)->unique();
            $table->string('name', 150);
            $table->string('cnpj', 14)->unique();

            $table->string('responsible_name', 150);
            $table->string('responsible_email', 254);
            $table->string('responsible_phone', 20);

            $table->string('status', 20)->default('trial')->index();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('overdue_since')->nullable()->index();
            $table->timestampTz('restrictions_applied_at')->nullable();

            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('stripe_customer_id', 255)->nullable()->unique();
            $table->string('subdomain_custom', 255)->nullable()->unique();

            $table->timestampTz('terms_accepted_at');
            $table->string('terms_version', 20);

            // PG aceita string JSON e faz cast implícito para jsonb na inserção.
            $table->jsonb('onboarding_state')->default('{}');

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // CHECK constraint emulando ENUM de status — protege a tabela
        // contra escritas com valores fora do conjunto canônico, mesmo
        // que cheguem por raw SQL (Princípio I).
        DB::statement(<<<'SQL'
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_status_check
            CHECK (status IN ('trial', 'active', 'overdue', 'suspended', 'cancelled'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

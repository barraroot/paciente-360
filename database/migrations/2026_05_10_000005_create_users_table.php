<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `users` — identidade unificada multi-tenant.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 4.
 *
 * Notas:
 *  - `tenant_id` é NULLABLE: NULL indica Super Admin (cross-tenant), que
 *    não pertence a nenhuma clínica.
 *  - Unicidade de e-mail é POR TENANT. COALESCE(tenant_id, 0) converte
 *    NULL em sentinela numérico para que o índice único funcione
 *    corretamente mesmo com múltiplos Super Admins (NULL + NULL colide
 *    em índices B-tree padrão, o COALESCE resolve).
 *  - `status` controlado via CHECK constraint (não ENUM PG) para permitir
 *    extensão de valores sem `ALTER TYPE` em produção.
 *  - Sem colunas 2FA: removido do MVP conforme constituição v1.3.0.
 *  - `last_login_ip` usa tipo INET nativo do PG (validação implícita).
 *  - `password_changed_at` omitido do data-model §4 mas incluído para
 *    suporte à política de rotação de senha (FR-022). Se rejeitado em
 *    review, remova nesta migration + adicione em migration futura.
 *  - Soft delete: `deleted_at` preserva auditoria ao desativar usuário
 *    (FR-028).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');

            $table->string('name', 150);
            $table->string('email', 254);
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->rememberToken();

            $table->string('status', 20)->default('invited');

            $table->timestampTz('first_login_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->smallInteger('failed_login_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // UNIQUE composto com COALESCE: mesmo email é aceito em tenants
        // distintos; bloqueado dentro do mesmo tenant. NULL (Super Admin)
        // é tratado como tenant "platform" via sentinela 0.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX users_email_tenant_unique
            ON users (email, COALESCE(tenant_id, 0))
        SQL);

        // Índice composto para listagem de usuários por tenant com filtro
        // de status (query principal do painel de gestão de usuários).
        DB::statement(<<<'SQL'
            CREATE INDEX users_tenant_id_status_idx
            ON users (tenant_id, status)
        SQL);

        // Índice simples em email para lookup no fluxo de login
        // (tenant é resolvido antes pelo subdomínio, email vem depois).
        DB::statement(<<<'SQL'
            CREATE INDEX users_email_idx ON users (email)
        SQL);

        // CHECK constraint emulando ENUM de status.
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_status_check
            CHECK (status IN ('invited', 'active', 'disabled'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

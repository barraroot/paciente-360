<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `invitations` — convites de usuário interno (US-2.2).
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 7.
 *
 * Notas:
 *  - Apenas `token_hash` é persistido; o token em claro vai SOMENTE no
 *    e-mail enviado ao convidado. Isso garante que um dump de banco não
 *    exponha tokens válidos (Princípio VII — segurança em profundidade).
 *  - `status` via CHECK constraint (não ENUM PG) para extensibilidade.
 *  - UNIQUE parcial `(tenant_id, email) WHERE status = 'pending'`: um
 *    e-mail só pode ter um convite pendente por tenant ao mesmo tempo;
 *    convites aceitos/expirados/revogados não bloqueiam re-convite.
 *  - FK `inviter_user_id` → `users.id` ON DELETE RESTRICT: não permite
 *    deletar o usuário enquanto houver convite vinculado (histórico legal).
 *  - FK `tenant_id` → `tenants.id` ON DELETE CASCADE: convites de um
 *    tenant deletado são automaticamente removidos (dados descartáveis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('email', 254);
            $table->string('intended_role', 50);

            $table->unsignedBigInteger('inviter_user_id');
            $table->foreign('inviter_user_id')->references('id')->on('users')->onDelete('restrict');

            $table->string('token_hash', 255);

            $table->string('status', 20)->default('pending');

            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();

            $table->timestampsTz();
        });

        // CHECK constraint emulando ENUM de status.
        DB::statement(<<<'SQL'
            ALTER TABLE invitations
            ADD CONSTRAINT invitations_status_check
            CHECK (status IN ('pending', 'accepted', 'expired', 'revoked'))
        SQL);

        // Índice para buscas de convites por tenant+email (consulta frequente
        // no painel de gestão e no fluxo de re-convite).
        DB::statement(<<<'SQL'
            CREATE INDEX invitations_tenant_email_idx ON invitations (tenant_id, email)
        SQL);

        // Índice para job de limpeza de convites expirados.
        DB::statement(<<<'SQL'
            CREATE INDEX invitations_expires_at_idx ON invitations (expires_at)
        SQL);

        // UNIQUE parcial: bloqueia segundo convite pendente para o mesmo
        // e-mail no mesmo tenant; aceita múltiplos históricos de outros status.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX invitations_tenant_email_pending_uniq
            ON invitations (tenant_id, email)
            WHERE status = 'pending'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

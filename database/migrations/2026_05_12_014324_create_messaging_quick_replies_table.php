<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T017 — Cria a tabela `messaging_quick_replies`.
 *
 * Respostas rápidas com escopo dual (NC-8):
 *  - owner_user_id IS NULL → tenant (compartilhada com todos)
 *  - owner_user_id NOT NULL → privada do usuário
 *
 * PostgreSQL trata NULL como distinto em UNIQUE, então o UNIQUE composto
 * (tenant_id, owner_user_id, shortcut) permite o mesmo atalho existir em
 * escopos diferentes (tenant X user_A X user_B).
 *
 * Partial indexes separam as duas listagens com cardinalidade diferente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_quick_replies', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('shortcut', 50);
            $table->text('content');
            $table->boolean('has_media')->default(false);
            $table->jsonb('variables_used')->default('[]');
            $table->integer('usage_count')->default(0);

            $table->timestampsTz();
        });

        // UNIQUE composto — NULL é distinto em PG: mesmo shortcut em escopos diferentes é válido
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX messaging_quick_replies_scope_shortcut_unique
            ON messaging_quick_replies (tenant_id, owner_user_id, shortcut)
        SQL);

        // Listar respostas privadas de um usuário
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_quick_replies_user_idx
            ON messaging_quick_replies (tenant_id, owner_user_id)
            WHERE owner_user_id IS NOT NULL
        SQL);

        // Listar respostas compartilhadas do tenant
        DB::statement(<<<'SQL'
            CREATE INDEX messaging_quick_replies_tenant_scope_idx
            ON messaging_quick_replies (tenant_id)
            WHERE owner_user_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_quick_replies');
    }
};

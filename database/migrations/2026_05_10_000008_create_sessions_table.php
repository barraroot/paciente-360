<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `sessions` — driver de sessão database do Laravel.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 13.
 *
 * Notas:
 *  - Schema padrão Laravel para SESSION_DRIVER=database.
 *  - `user_id` indexado para permitir kill remoto de sessões por usuário
 *    (FR-028: desativação de usuário deve invalidar sessões ativas).
 *  - `last_activity` indexado para garbage collection periódica de sessões
 *    expiradas (SessionTableCommand / GC nativo do Laravel).
 *  - `payload` como LONGTEXT (compatibilidade com driver padrão do Laravel
 *    que serializa a sessão inteira nesta coluna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};

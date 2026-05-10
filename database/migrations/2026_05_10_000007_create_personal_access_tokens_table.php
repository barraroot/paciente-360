<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `personal_access_tokens` — schema default do Laravel Sanctum.
 *
 * Schema conforme `specs/001-fundacao-multitenant/data-model.md` § 12.
 *
 * Notas:
 *  - Estrutura 100% padrão Sanctum; sem custom columns para não comprometer
 *    a compatibilidade interna do pacote.
 *  - Nesta fase, tokens são emitidos apenas para ferramentas internas
 *    (CLI super-admin, integração CI/test runner). A autenticação da SPA
 *    usa cookie de sessão Sanctum (não personal access token).
 *  - `tokenable` é polimórfico: permite emitir tokens para User ou qualquer
 *    outro Model (ex.: integrações de fase futura).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **T215 (Fase 8 — Lote D US-11.2)** — Clients OAuth 2.0 Client Credentials (Q18).
 *
 * Gated por `config('finalization.oauth_enabled')`. Migration sempre roda
 * (tabela vazia se OAuth desligado). Schema slim — NÃO usa Passport
 * `oauth_clients` (reservada) para evitar conflito futuro com Passport real.
 *
 * Quando Passport for instalado de fato (enterprise), este model espelha o
 * essencial; lifetimes/JWT signing ficam no Passport-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_oauth_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->uuid('client_id')->unique();
            // SHA-256 do client_secret (plaintext nunca persistido).
            $table->string('client_secret_hash');
            $table->jsonb('scopes');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_oauth_clients');
    }
};

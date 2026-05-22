<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **T087 (Fase 8 — Lote B US-12.1)** — Tabela `super_admin_audit_screens` (GLOBAL).
 *
 * Audit granular de cada tela visitada durante sessão de impersonate (Q19 / Gate 7).
 * Permite reconstruir EXATAMENTE quais dados o Super Admin viu durante uma sessão
 * de suporte — evidência LGPD obrigatória.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §2.4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admin_audit_screens', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('impersonate_session_id');
            $table->foreign('impersonate_session_id')
                ->references('id')->on('impersonate_sessions')
                ->onDelete('cascade');

            $table->string('route', 255);
            $table->string('path', 500);
            $table->string('method', 10);
            $table->timestampTz('visited_at');
            $table->ipAddress('ip_address');

            $table->json('query_params')->nullable();
            // Filtros aplicados (sem corpo); ajuda investigar "quais filtros o
            // SA usou para chegar a este dado".

            $table->timestampsTz();

            $table->index(['impersonate_session_id', 'visited_at'], 'idx_audit_screens_by_session');
            $table->index(['route', 'visited_at'], 'idx_audit_screens_by_route');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_audit_screens');
    }
};

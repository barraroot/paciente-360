<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — Adiciona coluna `executor_id` em `audit_logs`.
 *
 * Coluna extra de conveniência para rastrear o usuário executor em eventos
 * de canal (channel.connected, channel.disconnected) onde o `user_id` padrão
 * pode ser null (em contexto de webhook/sistema). Permite filtrar audit logs
 * pelo usuário que iniciou a ação sem ambiguidade de `actor_type`.
 *
 * NULL quando a ação foi disparada por sistema (job, webhook sem user).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('executor_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn('executor_id');
        });
    }
};

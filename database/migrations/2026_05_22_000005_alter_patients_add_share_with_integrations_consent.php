<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **T021 (Fase 8 — Lote A US-13.1)** — Adiciona `share_with_integrations_consent`
 * em `pacientes`.
 *
 * Consumido pelo `WebhookDispatcher` (Lote D — AC-11.1.7) para mascarar nome
 * do paciente em payloads de integração quando o paciente NÃO consentiu
 * compartilhamento com terceiros. Default `false` (conservador — opt-in
 * explícito é necessário para integração externa ver o nome real).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §1.5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table): void {
            $table->boolean('share_with_integrations_consent')
                ->default(false)
                ->after('updated_at')
                ->comment('Q AC-11.1.7 — consentimento opcional para compartilhar dados com integrações externas (webhooks/API pública). Default false.');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dropColumn('share_with_integrations_consent');
        });
    }
};

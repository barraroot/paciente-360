<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T004 (Spec 012)** — Estende `professionals` para gestão completa pelo painel.
 *
 * Adiciona:
 * - `especialidade` (VARCHAR 100 NULL) — campo opcional com autocomplete contra
 *   histórico do tenant (Q1 da spec).
 * - `council_type_other` (VARCHAR 50 NULL) — quando council_type='OUTRO',
 *   armazena o nome do conselho digitado (CREFITO/CFN/etc.). Validado via
 *   FormRequest (required_if council_type=OUTRO).
 * - UNIQUE PARCIAL `(tenant_id, council_type, council_number, council_state)
 *   WHERE deleted_at IS NULL` — bloqueia conselhos duplicados ativos no mesmo
 *   tenant. PARCIAL permite reuso do número após soft-delete.
 *
 * @see specs/012-professionals-management/research.md R1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table): void {
            $table->string('especialidade', 100)->nullable()->after('council_state');
            $table->string('council_type_other', 50)->nullable()->after('council_type');
            // pending_invitation_email: usado quando Professional é criado via
            // fluxo de convite (user_id NULL + is_active=false). O listener
            // ActivatePendingProfessionalOnInvitationAccepted faz lookup por
            // este campo quando InvitationAccepted dispara.
            $table->string('pending_invitation_email', 255)->nullable()->after('user_id');
            $table->index(['tenant_id', 'pending_invitation_email'], 'idx_prof_pending_email');
        });

        // UNIQUE PARCIAL — apenas para registros não-soft-deletados.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX professionals_council_unique_per_tenant
            ON professionals (tenant_id, council_type, council_number, council_state)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS professionals_council_unique_per_tenant');

        Schema::table('professionals', function (Blueprint $table): void {
            $table->dropIndex('idx_prof_pending_email');
            $table->dropColumn(['especialidade', 'council_type_other', 'pending_invitation_email']);
        });
    }
};

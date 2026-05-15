<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T009 — `schedule_exceptions` (US-6.1 — bloqueios pontuais — clarify nº 5).
 *
 * Schema conforme `specs/005-agendamento-consultas/data-model.md` § 4.
 *
 * Notas:
 *  - `created_by_user_id` para audit (médico próprio OR admin via `schedule.configure`).
 *  - GiST tsrange index para query de overlap eficiente
 *    ("este slot conflita com algum bloqueio?").
 *  - `tstzrange` é gerado via expression no índice — fica IMMUTABLE.
 *  - Listener cancela appointments sobrepostos (FR-028c).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->string('reason', 255)->nullable();

            $table->unsignedBigInteger('created_by_user_id');
            $table->foreign('created_by_user_id', 'se_creator_fk')
                ->references('id')->on('users')->onDelete('restrict');

            $table->timestampsTz();

            $table->index(['tenant_id', 'professional_id'], 'se_tenant_prof_idx');
        });

        DB::statement('
            ALTER TABLE schedule_exceptions
            ADD CONSTRAINT se_range_check
            CHECK (ends_at > starts_at)
        ');

        // GiST index para overlap queries (Postgres tsrange)
        // CRIA EXTENSION btree_gist se não existir (necessário para combinar bigint + range no mesmo índice)
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement("
            CREATE INDEX se_overlap_gist_idx
            ON schedule_exceptions
            USING GIST (
                tenant_id,
                professional_id,
                tstzrange(starts_at, ends_at, '[)')
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};

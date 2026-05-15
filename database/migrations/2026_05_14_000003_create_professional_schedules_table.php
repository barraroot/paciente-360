<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T008 — `professional_schedules` (US-6.1 — FR-001/004 + clarify nº 5).
 *
 * Schema conforme `specs/005-agendamento-consultas/data-model.md` § 3.
 *
 * Notas:
 *  - `blocks` JSONB — array `[{start: "08:00", end: "12:00"}, ...]`.
 *    Validação de formato/sobreposição em service layer (não em DB).
 *  - UNIQUE(tenant_id, professional_id, day_of_week, effective_from) garante
 *    "uma agenda por dia da semana por janela temporal".
 *  - `effective_from`/`effective_until` permite agendar mudança futura sem
 *    deletar a configuração atual.
 *  - `created_by_user_id` para audit (clarify nº 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_schedules', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');

            $table->smallInteger('day_of_week'); // 1=Mon..7=Sun (ISO 8601)

            $table->jsonb('blocks');

            $table->date('effective_from')->default(DB::raw('CURRENT_DATE'));
            $table->date('effective_until')->nullable();

            $table->unsignedBigInteger('created_by_user_id');
            $table->foreign('created_by_user_id', 'ps_creator_fk')
                ->references('id')->on('users')->onDelete('restrict');

            $table->timestampsTz();

            $table->unique(
                ['tenant_id', 'professional_id', 'day_of_week', 'effective_from'],
                'ps_tenant_prof_dow_from_unique'
            );
            $table->index(['tenant_id', 'professional_id'], 'ps_tenant_prof_idx');
        });

        DB::statement('
            ALTER TABLE professional_schedules
            ADD CONSTRAINT ps_day_of_week_check
            CHECK (day_of_week BETWEEN 1 AND 7)
        ');
        DB::statement('
            ALTER TABLE professional_schedules
            ADD CONSTRAINT ps_effective_range_check
            CHECK (effective_until IS NULL OR effective_until >= effective_from)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_schedules');
    }
};

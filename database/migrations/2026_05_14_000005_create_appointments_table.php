<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T010 — `appointments` (entidade central — US-6.3..6.7).
 *
 * Schema conforme `specs/005-agendamento-consultas/data-model.md` § 5.
 *
 * Pontos críticos:
 *
 *  1) **PARTIAL UNIQUE INDEX (gate atômico de race — FR-011a / SC-008)**:
 *     UNIQUE(tenant_id, professional_id, starts_at) WHERE status IN ('scheduled','confirmed').
 *     `realizada`/`nao_realizada`/`canceled`/`concluida_sem_registro` ficam fora — clarify nº 7
 *     confirmou que reschedule preserva status, sem 'reagendada'. Status terminais não bloqueiam
 *     nova criação no mesmo slot em datas futuras.
 *
 *  2) **`notes` ENCRYPTED** via Eloquent cast (Princípio I — não confiar em ORM apenas;
 *     o cast `encrypted` aplica `Crypt::encryptString` antes de persistir).
 *
 *  3) **`idempotency_key` UUID UNIQUE NULLABLE** (R8) — IA Matricial sempre envia;
 *     painel humano omite. Retry com mesma chave devolve 200 + flag `idempotent_replay`.
 *
 *  4) **`channel_origin`** rastreia se consulta veio de painel/IA/autoatendimento — alimenta
 *     métricas Prometheus (clarify de roadmap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->uuid('idempotency_key')->nullable()->unique();

            $table->unsignedBigInteger('paciente_id');
            $table->foreign('paciente_id')->references('id')->on('pacientes')->onDelete('restrict');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('restrict');

            $table->unsignedBigInteger('appointment_type_id');
            $table->foreign('appointment_type_id', 'app_type_fk')
                ->references('id')->on('appointment_types')->onDelete('restrict');

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->string('status', 32)->default('scheduled');
            $table->string('channel_origin', 32);

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id', 'app_creator_fk')
                ->references('id')->on('users')->onDelete('set null');

            $table->decimal('valor_aplicado', 10, 2);
            $table->text('valor_override_motivo')->nullable();

            $table->boolean('override_block')->default(false);
            $table->text('override_motivo')->nullable();

            $table->text('motivo_cancelamento')->nullable();
            $table->string('quem_cancelou', 32)->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();

            // Marcação de comparecimento (clarify nº 14)
            $table->timestampTz('attendance_marked_at')->nullable();
            $table->unsignedBigInteger('attendance_marked_by_user_id')->nullable();
            $table->foreign('attendance_marked_by_user_id', 'app_attmarker_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->text('attendance_motivo')->nullable();
            $table->timestampTz('auto_flagged_at')->nullable();

            // notes — encrypted via cast em Eloquent Model (Princípio I)
            $table->text('notes')->nullable();

            $table->timestampsTz();

            // Índices
            $table->index(['tenant_id', 'paciente_id', 'starts_at'], 'app_tenant_paciente_starts_idx');
            $table->index(['tenant_id', 'status', 'starts_at'], 'app_tenant_status_starts_idx');
            $table->index(['tenant_id', 'professional_id', 'starts_at'], 'app_tenant_prof_starts_idx');
        });

        // CHECK constraints
        DB::statement('
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_range_check
            CHECK (ends_at > starts_at)
        ');

        DB::statement("
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_status_check
            CHECK (status IN ('scheduled', 'confirmed', 'canceled', 'realizada', 'nao_realizada', 'concluida_sem_registro'))
        ");

        DB::statement("
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_channel_origin_check
            CHECK (channel_origin IN ('painel', 'ia', 'autoatendimento'))
        ");

        DB::statement("
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_quem_cancelou_check
            CHECK (quem_cancelou IS NULL OR quem_cancelou IN ('paciente', 'atendente', 'profissional', 'sistema'))
        ");

        // *** GATE FR-011a / SC-008 ***
        // Partial UNIQUE só sobre status ativos (scheduled, confirmed).
        // Status terminais (realizada, nao_realizada, canceled, concluida_sem_registro)
        // ficam fora — clarify nº 7 confirma que reschedule preserva status sem criar 'reagendada'.
        DB::statement("
            CREATE UNIQUE INDEX app_active_slot_unique
            ON appointments (tenant_id, professional_id, starts_at)
            WHERE status IN ('scheduled', 'confirmed')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};

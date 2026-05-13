<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T013 — `waitlist_entries` (lista de espera FIFO sequencial K=1 — clarify nº 8).
 *
 * Schema conforme `specs/005-agendamento-consultas/data-model.md` § 8.
 *
 * Pontos:
 *  - `position` derivado FIFO no enroll() do service.
 *  - PARTIAL INDEX em (status, expires_at) WHERE status='notified' acelera cron
 *    `agenda:expire-waitlist-notifications`.
 *  - Múltiplas entries permitidas por paciente em (prof×tipo) distintos (AC-6.6.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('paciente_id');
            $table->foreign('paciente_id')->references('id')->on('pacientes')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');

            $table->unsignedBigInteger('appointment_type_id');
            $table->foreign('appointment_type_id', 'we_appt_type_fk')
                ->references('id')->on('appointment_types')->onDelete('cascade');

            $table->string('status', 16)->default('waiting');
            $table->integer('position');

            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('notified_for_slot_starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();

            $table->unsignedBigInteger('accepted_appointment_id')->nullable();
            $table->foreign('accepted_appointment_id', 'we_accepted_app_fk')
                ->references('id')->on('appointments')->onDelete('set null');

            $table->timestampsTz();

            $table->index(
                ['tenant_id', 'professional_id', 'appointment_type_id', 'status', 'position'],
                'we_queue_lookup_idx'
            );
            $table->index(['tenant_id', 'paciente_id'], 'we_tenant_paciente_idx');
        });

        DB::statement("
            ALTER TABLE waitlist_entries
            ADD CONSTRAINT we_status_check
            CHECK (status IN ('waiting', 'notified', 'accepted', 'expired', 'canceled'))
        ");

        // PARTIAL INDEX para cron expire-waitlist-notifications
        DB::statement("
            CREATE INDEX we_notified_expires_idx
            ON waitlist_entries (expires_at)
            WHERE status = 'notified'
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};

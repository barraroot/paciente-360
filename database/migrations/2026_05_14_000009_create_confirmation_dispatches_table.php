<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T014 — `confirmation_dispatches` (US-6.4 — clarify nº 6).
 *
 * Cada disparo de confirmação (T-24h, T-2h, retry T-30min, escala T-15min) gera 1 row.
 * UNIQUE(appointment_id, kind) impede duplicação acidental.
 *
 * `via_ia=true` quando paciente tem conversa ativa com IA — Fase 5 emite o evento mas
 * NÃO envia template (IA assume).
 *
 * `status='pending_manual'` quando paciente sem canal OU T-15min sem resposta
 * (clarify nº 6 — A1 do analyze: NÃO muda Appointment.status).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmation_dispatches', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('appointment_id');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');

            $table->string('kind', 32);
            $table->boolean('via_ia')->default(false);

            $table->timestampTz('dispatched_at');
            $table->timestampTz('response_received_at')->nullable();
            $table->string('response_value', 8)->nullable();

            $table->string('status', 24)->default('dispatched');

            $table->timestampsTz();

            $table->unique(['appointment_id', 'kind'], 'cd_appointment_kind_unique');
            $table->index(['tenant_id', 'status', 'dispatched_at'], 'cd_tenant_status_idx');
        });

        DB::statement("
            ALTER TABLE confirmation_dispatches
            ADD CONSTRAINT cd_kind_check
            CHECK (kind IN ('24h', '2h', 'retry_30min', '15min_manual_escalation'))
        ");

        DB::statement("
            ALTER TABLE confirmation_dispatches
            ADD CONSTRAINT cd_response_value_check
            CHECK (response_value IS NULL OR response_value IN ('1', '2', '3'))
        ");

        DB::statement("
            ALTER TABLE confirmation_dispatches
            ADD CONSTRAINT cd_status_check
            CHECK (status IN ('dispatched', 'confirmed', 'reschedule_requested', 'canceled', 'pending_manual'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmation_dispatches');
    }
};

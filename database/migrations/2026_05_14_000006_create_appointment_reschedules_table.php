<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T011 — `appointment_reschedules` (histórico — clarify nº 7).
 *
 * Base para enforcement do limite de reagendamentos (FR-026b/c) via COUNT(*).
 * Cada reagendamento bem sucedido grava 1 row aqui (idempotência por idempotency_key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reschedules', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('appointment_id');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');

            $table->uuid('idempotency_key')->nullable()->unique();

            $table->timestampTz('starts_at_anterior');
            $table->timestampTz('starts_at_novo');

            $table->string('quem_solicitou', 32);
            $table->text('motivo')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->index(['appointment_id', 'created_at'], 'ar_appointment_created_idx');
            $table->index(['tenant_id', 'created_at'], 'ar_tenant_created_idx');
        });

        DB::statement("
            ALTER TABLE appointment_reschedules
            ADD CONSTRAINT ar_quem_solicitou_check
            CHECK (quem_solicitou IN ('paciente', 'atendente', 'profissional', 'ia'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reschedules');
    }
};

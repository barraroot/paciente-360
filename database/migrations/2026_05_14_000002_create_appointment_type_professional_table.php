<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T007 — Pivot M2M `appointment_type_professional` (US-6.1 / FR-003).
 *
 * Carrega `tenant_id` denormalizado para gate Princípio II em queries diretas
 * sem precisar JOIN; PK composite garante unicidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_type_professional', function (Blueprint $table): void {
            $table->unsignedBigInteger('appointment_type_id');
            $table->foreign('appointment_type_id', 'atp_appointment_type_fk')
                ->references('id')->on('appointment_types')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id', 'atp_professional_fk')
                ->references('id')->on('professionals')->onDelete('cascade');

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'atp_tenant_fk')
                ->references('id')->on('tenants')->onDelete('cascade');

            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['appointment_type_id', 'professional_id'], 'atp_pk');
            $table->index(['tenant_id', 'professional_id'], 'atp_tenant_professional_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_type_professional');
    }
};

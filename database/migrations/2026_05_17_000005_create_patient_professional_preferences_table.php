<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_professional_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('cascade');
            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('users')->onDelete('cascade');
            $table->boolean('suppress_renewal_notifications')->default(false);
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE patient_professional_preferences
            ADD CONSTRAINT uq_patient_professional
            UNIQUE (patient_id, professional_id)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_pp_pref_tenant
            ON patient_professional_preferences (tenant_id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_professional_preferences');
    }
};

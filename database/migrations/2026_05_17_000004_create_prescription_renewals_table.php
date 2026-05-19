<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'initiated_by_enum') THEN
                    CREATE TYPE initiated_by_enum AS ENUM ('professional', 'ai', 'patient');
                END IF;
            END
            $$;
        SQL);

        Schema::create('prescription_renewals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unsignedBigInteger('original_prescription_id');
            $table->foreign('original_prescription_id')->references('id')->on('prescriptions')->onDelete('cascade');
            $table->unsignedBigInteger('renewed_prescription_id')->nullable();
            $table->foreign('renewed_prescription_id')->references('id')->on('prescriptions')->nullOnDelete();
            $table->string('initiated_by');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE prescription_renewals ALTER COLUMN initiated_by TYPE initiated_by_enum USING initiated_by::initiated_by_enum');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_prescription_renewals_original_completed
            ON prescription_renewals (original_prescription_id)
            WHERE renewed_prescription_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE prescription_renewals
            ADD CONSTRAINT chk_renewal_distinct
            CHECK (
                original_prescription_id IS NULL
                OR renewed_prescription_id IS NULL
                OR original_prescription_id != renewed_prescription_id
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescription_renewals_original
            ON prescription_renewals (original_prescription_id)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescription_renewals_appointment
            ON prescription_renewals (appointment_id)
            WHERE appointment_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescription_renewals_ai_metric
            ON prescription_renewals (tenant_id, created_at)
            WHERE initiated_by = 'ai'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_renewals');
        DB::statement('DROP TYPE IF EXISTS initiated_by_enum');
    }
};

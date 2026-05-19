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
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'prescription_type_enum') THEN
                    CREATE TYPE prescription_type_enum AS ENUM ('common', 'special', 'controlled');
                END IF;
            END
            $$;
        SQL);
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'prescription_status_enum') THEN
                    CREATE TYPE prescription_status_enum AS ENUM ('active', 'cancelled', 'superseded');
                END IF;
            END
            $$;
        SQL);
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'prescription_source_enum') THEN
                    CREATE TYPE prescription_source_enum AS ENUM ('manual', 'import', 'ai');
                END IF;
            END
            $$;
        SQL);
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'cancellation_reason_category_enum') THEN
                    CREATE TYPE cancellation_reason_category_enum AS ENUM ('erro_emissao', 'desistencia_paciente', 'substituicao', 'outro');
                END IF;
            END
            $$;
        SQL);

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('restrict');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();

            $table->string('type')->default('common');
            $table->string('status')->default('active');
            $table->date('issued_at');
            $table->date('expires_at');

            $table->unsignedBigInteger('renewed_from_id')->nullable();
            $table->foreign('renewed_from_id')->references('id')->on('prescriptions')->nullOnDelete();

            $table->string('source')->default('manual');
            $table->timestampTz('cancelled_at')->nullable();

            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->foreign('cancelled_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('cancellation_reason_category')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('alert_disabled')->default(false);
            $table->text('notes')->nullable();
            $table->string('pdf_path', 512)->nullable();
            $table->smallInteger('pdf_version')->default(0);
            $table->timestampTz('imported_at')->nullable();
            $table->string('imported_source', 100)->nullable();
            $table->string('historical_external_id', 255)->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'expires_at'], 'idx_prescriptions_tenant_status_expires');
            $table->index(['tenant_id', 'type', 'expires_at'], 'idx_prescriptions_tenant_type_expires');
            $table->index(['tenant_id', 'professional_id', 'expires_at'], 'idx_prescriptions_tenant_professional');
            $table->index(['tenant_id', 'patient_id', 'issued_at'], 'idx_prescriptions_tenant_patient');
        });

        DB::statement('ALTER TABLE prescriptions ALTER COLUMN type DROP DEFAULT');
        DB::statement('ALTER TABLE prescriptions ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE prescriptions ALTER COLUMN source DROP DEFAULT');
        DB::statement('ALTER TABLE prescriptions ALTER COLUMN type TYPE prescription_type_enum USING type::prescription_type_enum');
        DB::statement('ALTER TABLE prescriptions ALTER COLUMN status TYPE prescription_status_enum USING status::prescription_status_enum');
        DB::statement('ALTER TABLE prescriptions ALTER COLUMN source TYPE prescription_source_enum USING source::prescription_source_enum');
        DB::statement('ALTER TABLE prescriptions ALTER COLUMN cancellation_reason_category DROP DEFAULT');
        DB::statement(
            'ALTER TABLE prescriptions ALTER COLUMN cancellation_reason_category TYPE cancellation_reason_category_enum '.
            'USING cancellation_reason_category::cancellation_reason_category_enum'
        );
        DB::statement("ALTER TABLE prescriptions ALTER COLUMN type SET DEFAULT 'common'");
        DB::statement("ALTER TABLE prescriptions ALTER COLUMN status SET DEFAULT 'active'");
        DB::statement("ALTER TABLE prescriptions ALTER COLUMN source SET DEFAULT 'manual'");

        DB::statement(<<<'SQL'
            ALTER TABLE prescriptions
            ADD CONSTRAINT chk_prescription_validity_by_type
            CHECK (
                (type IN ('special', 'controlled') AND (expires_at - issued_at) = 30)
                OR
                (
                    type = 'common'
                    AND (expires_at - issued_at) BETWEEN 30 AND 180
                    AND (expires_at - issued_at) IN (30, 60, 90, 180)
                )
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE prescriptions
            ADD CONSTRAINT chk_prescription_expiry_after_issue
            CHECK (expires_at >= issued_at)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE prescriptions
            ADD CONSTRAINT chk_prescription_alert_disabled_only_common
            CHECK (alert_disabled = false OR type = 'common')
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE prescriptions
            ADD CONSTRAINT chk_prescription_cancellation_consistency
            CHECK (
                (
                    status = 'cancelled'
                    AND cancelled_at IS NOT NULL
                    AND cancellation_reason_category IS NOT NULL
                    AND cancelled_by_user_id IS NOT NULL
                )
                OR
                (status != 'cancelled' AND cancelled_at IS NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_prescriptions_historical_per_tenant
            ON prescriptions (tenant_id, historical_external_id)
            WHERE historical_external_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescriptions_controlled_audit
            ON prescriptions (tenant_id, expires_at, status)
            WHERE type = 'controlled'
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescriptions_renewed_from
            ON prescriptions (renewed_from_id)
            WHERE renewed_from_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescriptions_active_expiring
            ON prescriptions (expires_at, status)
            WHERE status = 'active' AND alert_disabled = false
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');

        DB::statement('DROP TYPE IF EXISTS cancellation_reason_category_enum');
        DB::statement('DROP TYPE IF EXISTS prescription_source_enum');
        DB::statement('DROP TYPE IF EXISTS prescription_status_enum');
        DB::statement('DROP TYPE IF EXISTS prescription_type_enum');
    }
};

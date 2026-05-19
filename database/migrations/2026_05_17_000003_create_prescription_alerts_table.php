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
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'alert_type_enum') THEN
                    CREATE TYPE alert_type_enum AS ENUM ('days15', 'days7', 'days1');
                END IF;
            END
            $$;
        SQL);
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'alert_status_enum') THEN
                    CREATE TYPE alert_status_enum AS ENUM ('pending', 'dispatched', 'blocked_no_channel', 'blocked_no_template', 'skipped', 'cancelled', 'failed');
                END IF;
            END
            $$;
        SQL);

        Schema::create('prescription_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prescription_id');
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->string('alert_type');
            $table->string('status')->default('pending');
            $table->date('scheduled_for');
            $table->timestampTz('dispatched_at')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->foreign('channel_id')->references('id')->on('messaging_channels')->nullOnDelete();
            $table->string('message_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('skip_reason', 100)->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE prescription_alerts ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE prescription_alerts ALTER COLUMN alert_type TYPE alert_type_enum USING alert_type::alert_type_enum');
        DB::statement('ALTER TABLE prescription_alerts ALTER COLUMN status TYPE alert_status_enum USING status::alert_status_enum');
        DB::statement("ALTER TABLE prescription_alerts ALTER COLUMN status SET DEFAULT 'pending'");

        DB::statement(<<<'SQL'
            ALTER TABLE prescription_alerts
            ADD CONSTRAINT uq_prescription_alerts_idempotency
            UNIQUE (prescription_id, alert_type)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescription_alerts_dispatch_queue
            ON prescription_alerts (scheduled_for, status)
            WHERE status = 'pending'
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescription_alerts_tenant_status
            ON prescription_alerts (tenant_id, status, scheduled_for DESC)
            WHERE status IN ('failed', 'blocked_no_template', 'blocked_no_channel')
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_prescription_alerts_prescription
            ON prescription_alerts (prescription_id, alert_type)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_alerts');
        DB::statement('DROP TYPE IF EXISTS alert_status_enum');
        DB::statement('DROP TYPE IF EXISTS alert_type_enum');
    }
};

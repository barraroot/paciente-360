<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T152 (Fase 8 — Lote C US-9.1)** — Tabela `campaigns` (TENANT-SCOPED).
 *
 * Disparo agendado ou imediato com critério de segmentação. Q3 canal único
 * por campanha — multi-canal exige campanhas separadas.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §3.1
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'campaign_status_enum') THEN
                    CREATE TYPE campaign_status_enum AS ENUM ('draft', 'scheduled', 'dispatching', 'completed', 'canceled');
                END IF;
            END
            $$;
        SQL);
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'campaign_channel_enum') THEN
                    CREATE TYPE campaign_channel_enum AS ENUM ('whatsapp', 'instagram', 'sms_future');
                END IF;
            END
            $$;
        SQL);

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('name', 200);
            $table->string('status', 20)->default('draft');
            $table->string('channel', 20);

            $table->unsignedBigInteger('template_id')->nullable();
            $table->foreign('template_id')
                ->references('id')->on('messaging_channel_templates')
                ->nullOnDelete();

            $table->json('audience_filters');
            // {inactivity_months, tags[], last_professional_id, age_range, gender, last_procedure_type_id}

            $table->timestampTz('scheduled_for')->nullable();
            $table->timestampTz('dispatched_at')->nullable();

            $table->integer('total_eligible')->nullable();
            $table->integer('total_dispatched')->default(0);
            $table->integer('total_blocked')->default(0);

            $table->integer('daily_limit_applied');
            // Snapshot do plan.daily_campaign_limit no momento do dispatch (Q2)

            $table->timestampTz('canceled_at')->nullable();
            $table->unsignedBigInteger('canceled_by_user_id')->nullable();
            $table->foreign('canceled_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->text('canceled_reason')->nullable();

            $table->unsignedBigInteger('created_by_user_id');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('restrict');

            $table->timestampsTz();

            $table->index(['tenant_id', 'status'], 'idx_campaigns_tenant_status');
            $table->index(['tenant_id', 'created_at'], 'idx_campaigns_tenant_created');
        });

        DB::statement('ALTER TABLE campaigns ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE campaigns ALTER COLUMN status TYPE campaign_status_enum USING status::campaign_status_enum');
        DB::statement("ALTER TABLE campaigns ALTER COLUMN status SET DEFAULT 'draft'");
        DB::statement('ALTER TABLE campaigns ALTER COLUMN channel TYPE campaign_channel_enum USING channel::campaign_channel_enum');

        // PARTIAL INDEX — cron dispatch-scheduled busca campanhas scheduled.
        DB::statement(<<<'SQL'
            CREATE INDEX idx_campaigns_scheduled_dispatch
            ON campaigns (tenant_id, scheduled_for)
            WHERE status = 'scheduled'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
        DB::statement('DROP TYPE IF EXISTS campaign_channel_enum');
        DB::statement('DROP TYPE IF EXISTS campaign_status_enum');
    }
};

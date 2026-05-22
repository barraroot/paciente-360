<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T153 (Fase 8 — Lote C US-9.1)** — Tabela `campaign_recipients` (TENANT-SCOPED).
 *
 * Snapshot de cada destinatário no momento do disparo. UNIQUE
 * `(campaign_id, patient_id)` garante idempotência absoluta — re-disparo
 * acidental NUNCA envia 2× ao mesmo paciente (AC-9.1.6).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §3.2
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'campaign_recipient_status_enum') THEN
                    CREATE TYPE campaign_recipient_status_enum AS ENUM (
                        'pending', 'sent', 'delivered', 'read', 'responded', 'blocked', 'failed'
                    );
                END IF;
            END
            $$;
        SQL);

        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('campaign_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('restrict');

            $table->timestampTz('dispatched_at')->nullable();
            $table->string('status', 20)->default('pending');

            $table->string('blocked_reason', 50)->nullable();
            // no_marketing_opt_in | no_template_approved | outside_business_hours |
            // daily_limit_exceeded | no_reachable_channel | sair_received_24h |
            // template_no_longer_approved

            $table->string('external_message_id', 255)->nullable();
            // ID retornado pela Meta/Twilio após envio bem-sucedido.

            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('responded_at')->nullable();

            $table->unsignedBigInteger('attributed_appointment_id')->nullable();
            $table->foreign('attributed_appointment_id')
                ->references('id')->on('appointments')
                ->nullOnDelete();
            // SC-9.3 — agendamento atribuível: paciente respondeu + agendou ≤ 7d.

            $table->timestampsTz();

            $table->unique(['campaign_id', 'patient_id'], 'uq_campaign_recipients_idempotency');
            // Idempotência absoluta — Q AC-9.1.6.

            $table->index(['tenant_id', 'campaign_id', 'status'], 'idx_campaign_recipients_status');
            $table->index(['tenant_id', 'patient_id', 'dispatched_at'], 'idx_campaign_recipients_timeline');
        });

        DB::statement('ALTER TABLE campaign_recipients ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE campaign_recipients ALTER COLUMN status TYPE campaign_recipient_status_enum USING status::campaign_recipient_status_enum');
        DB::statement("ALTER TABLE campaign_recipients ALTER COLUMN status SET DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        DB::statement('DROP TYPE IF EXISTS campaign_recipient_status_enum');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 013 — Rastreio de cada tentativa de notificar um paciente.
 *
 * Liga `message_id` (reconciliação de status) e `source_type/source_id`
 * (morph para Appointment/Prescription/WaitlistEntry).
 *
 * @see specs/013-outbound-notifications/data-model.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('pacientes')->cascadeOnDelete();
            $table->string('notification_type', 40);
            $table->string('milestone', 40);
            $table->string('status', 20)->default('queued');
            $table->string('skip_reason', 30)->nullable();

            $table->foreignId('channel_id')->nullable()
                ->constrained('messaging_channels')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()
                ->constrained('messaging_conversations')->nullOnDelete();
            $table->foreignId('notification_template_id')->nullable()
                ->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('message_id')->nullable()
                ->constrained('messaging_messages')->nullOnDelete();

            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        // Idempotência (FR-014): mesmo (paciente, tipo, marco, data) não duplica.
        // Estados `skipped` ficam fora — supressões não bloqueiam re-tentativa válida.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX outbound_notifications_idempotency_unique
            ON outbound_notifications (tenant_id, patient_id, notification_type, milestone, (created_at::date))
            WHERE status <> 'skipped'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_notifications');
    }
};

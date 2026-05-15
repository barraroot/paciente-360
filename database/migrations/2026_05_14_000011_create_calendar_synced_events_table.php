<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T016 — `calendar_synced_events` (mapping Appointment ↔ external event_id — US-6.7).
 *
 * Mantém o vínculo entre cada Appointment e o evento equivalente no Google.
 * Reaproveitado em reconexão (R5) — se sub-cal ainda existe no Google, retomamos sync sem
 * duplicar eventos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_synced_events', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('appointment_id');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');

            $table->unsignedBigInteger('calendar_sync_account_id');
            $table->foreign('calendar_sync_account_id', 'cse_csa_fk')
                ->references('id')->on('calendar_sync_accounts')->onDelete('cascade');

            $table->string('external_event_id', 255);
            $table->timestampTz('last_synced_at');
            $table->string('etag', 128)->nullable();

            $table->timestampsTz();

            // 1 vínculo por (consulta × conta)
            $table->unique(
                ['appointment_id', 'calendar_sync_account_id'],
                'cse_app_csa_unique'
            );

            // Lookup quando push notification chega
            $table->index(
                ['calendar_sync_account_id', 'external_event_id'],
                'cse_csa_external_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_synced_events');
    }
};

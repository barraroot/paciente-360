<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T017 — `external_calendar_busy` (clarify nº 10).
 *
 * Eventos externos no sub-calendário Google **não viram Appointment** —
 * apenas bloqueiam slot no gerador de slots.
 *
 * `summary_redacted` é apenas para debug interno (sanitizado, truncado, nunca exibido
 * ao paciente). Visualizado no painel como "Evento externo" sem detalhes.
 *
 * GiST tsrange index para query "este slot está ocupado externamente?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_calendar_busy', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');

            $table->unsignedBigInteger('calendar_sync_account_id');
            $table->foreign('calendar_sync_account_id', 'ecb_csa_fk')
                ->references('id')->on('calendar_sync_accounts')->onDelete('cascade');

            $table->string('external_event_id', 255);

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->string('provider', 16);
            $table->string('summary_redacted', 255)->nullable();
            $table->timestampTz('synced_at');

            $table->timestampsTz();

            // Dedup: 1 row por evento externo
            $table->unique(
                ['calendar_sync_account_id', 'external_event_id'],
                'ecb_csa_external_unique'
            );
        });

        DB::statement("
            ALTER TABLE external_calendar_busy
            ADD CONSTRAINT ecb_provider_check
            CHECK (provider IN ('google', 'outlook'))
        ");

        DB::statement('
            ALTER TABLE external_calendar_busy
            ADD CONSTRAINT ecb_range_check
            CHECK (ends_at > starts_at)
        ');

        // GiST index para overlap query — reusa extension btree_gist criada em T009
        DB::statement("
            CREATE INDEX ecb_overlap_gist_idx
            ON external_calendar_busy
            USING GIST (
                tenant_id,
                professional_id,
                tstzrange(starts_at, ends_at, '[)')
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('external_calendar_busy');
    }
};

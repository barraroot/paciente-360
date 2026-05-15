<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T012 — `slot_reservations` (reserva pessimista soft — clarify nº 2 / R4).
 *
 * Schema conforme `specs/005-agendamento-consultas/data-model.md` § 7.
 *
 * Pontos críticos:
 *
 *  1) **PARTIAL UNIQUE INDEX (gate de "uma reserva ativa por slot")**:
 *     UNIQUE(tenant_id, professional_id, starts_at) WHERE released_at IS NULL.
 *     Reservas históricas (released_at NOT NULL) ficam fora do índice — não inflam B-tree.
 *
 *  2) **PARTIAL INDEX em expires_at WHERE released_at IS NULL**: cron
 *     `agenda:cleanup-expired-reservations` (every minute) busca eficientemente
 *     reservas que precisam expirar.
 *
 *  3) **TTL diferenciado por holder**: 5 min user / 2 min IA
 *     (configurável em tenants.settings.agenda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_reservations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');

            $table->unsignedBigInteger('appointment_type_id');
            $table->foreign('appointment_type_id', 'sr_appt_type_fk')
                ->references('id')->on('appointment_types')->onDelete('cascade');

            $table->timestampTz('starts_at');

            $table->string('holder_type', 8); // 'user' | 'ia'
            $table->string('holder_id', 64);  // user_id (string) ou conversation_id (IA)

            $table->uuid('idempotency_key')->nullable()->unique();

            $table->timestampTz('acquired_at')->useCurrent();
            $table->timestampTz('expires_at');

            $table->timestampTz('released_at')->nullable();
            $table->string('release_reason', 16)->nullable(); // committed|expired|canceled

            // Indexes
            $table->index(['holder_type', 'holder_id'], 'sr_holder_idx');
        });

        DB::statement("
            ALTER TABLE slot_reservations
            ADD CONSTRAINT sr_holder_type_check
            CHECK (holder_type IN ('user', 'ia'))
        ");

        DB::statement("
            ALTER TABLE slot_reservations
            ADD CONSTRAINT sr_release_reason_check
            CHECK (release_reason IS NULL OR release_reason IN ('committed', 'expired', 'canceled'))
        ");

        // *** Gate atômico: 1 reserva ativa por slot ***
        DB::statement('
            CREATE UNIQUE INDEX sr_active_unique
            ON slot_reservations (tenant_id, professional_id, starts_at)
            WHERE released_at IS NULL
        ');

        // Index para cron de cleanup
        DB::statement('
            CREATE INDEX sr_active_expires_idx
            ON slot_reservations (expires_at)
            WHERE released_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_reservations');
    }
};

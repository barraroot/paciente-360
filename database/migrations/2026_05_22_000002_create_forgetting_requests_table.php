<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T040 (Fase 8 — Lote A US-13.2)** — Tabela `forgetting_requests`.
 *
 * Solicitação formal de Direito ao Esquecimento (LGPD Art. 18º).
 * Deadline D+15 dias úteis a partir de `requested_at`. Status terminal:
 *   - `executed` — anonimização aplicada com sucesso.
 *   - `expired`  — deadline cruzou sem execução (alerta crítico).
 *   - `denied`   — verificação de identidade falhou.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §1.2
 * @see specs/008-finalizacao-mvp/research.md §1 Q26/Q27
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'forgetting_status_enum') THEN
                    CREATE TYPE forgetting_status_enum AS ENUM (
                        'open',
                        'pending_verification',
                        'in_progress',
                        'executed',
                        'expired',
                        'denied'
                    );
                END IF;
            END
            $$;
        SQL);

        Schema::create('forgetting_requests', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('restrict');

            $table->timestampTz('requested_at');
            $table->timestampTz('deadline_at');
            $table->string('channel_of_request', 50);
            $table->string('status', 30)->default('open');

            $table->timestampTz('executed_at')->nullable();
            $table->unsignedBigInteger('executed_by_user_id')->nullable();
            $table->foreign('executed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->json('fields_anonymized')->nullable();
            $table->json('fields_deleted')->nullable();
            $table->json('fields_preserved_reason')->nullable();

            $table->text('denial_reason')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'deadline_at'], 'idx_forgetting_requests_pending');
            $table->index(['deadline_at'], 'idx_forgetting_requests_deadline');
            $table->index(['tenant_id', 'patient_id'], 'idx_forgetting_requests_patient');
        });

        DB::statement('ALTER TABLE forgetting_requests ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE forgetting_requests ALTER COLUMN status TYPE forgetting_status_enum USING status::forgetting_status_enum');
        DB::statement("ALTER TABLE forgetting_requests ALTER COLUMN status SET DEFAULT 'open'");

        // Consistência executed_* só quando status='executed'.
        DB::statement(<<<'SQL'
            ALTER TABLE forgetting_requests
            ADD CONSTRAINT chk_forgetting_executed_consistency
            CHECK (
                (status = 'executed' AND executed_at IS NOT NULL AND executed_by_user_id IS NOT NULL)
                OR
                (status != 'executed')
            )
        SQL);

        // Deadline DEVE ser >= requested_at.
        DB::statement(<<<'SQL'
            ALTER TABLE forgetting_requests
            ADD CONSTRAINT chk_forgetting_deadline_after_request
            CHECK (deadline_at >= requested_at)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('forgetting_requests');
        DB::statement('DROP TYPE IF EXISTS forgetting_status_enum');
    }
};

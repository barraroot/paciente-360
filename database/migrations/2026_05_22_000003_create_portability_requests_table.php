<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T041 (Fase 8 — Lote A US-13.2)** — Tabela `portability_requests`.
 *
 * Solicitação de Portabilidade de Dados (Q28 — LGPD Art. 18º V).
 * Status terminal:
 *   - `ready` — arquivo gerado, URL assinada disponível por 7 dias.
 *   - `downloaded` — paciente baixou pelo menos uma vez.
 *   - `expired` — URL passou de 7 dias (paciente pode pedir novo link).
 *
 * `file_signed_url_id` é um UUID público usado na URL pelo controller —
 * permite revogação sem mudar o `file_path` e dificulta enumeration.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §1.3
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'portability_status_enum') THEN
                    CREATE TYPE portability_status_enum AS ENUM (
                        'open',
                        'generating',
                        'ready',
                        'downloaded',
                        'expired',
                        'denied'
                    );
                END IF;
            END
            $$;
        SQL);

        Schema::create('portability_requests', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('restrict');

            $table->timestampTz('requested_at');
            $table->timestampTz('deadline_at');
            $table->string('status', 30)->default('open');

            $table->timestampTz('executed_at')->nullable();
            $table->unsignedBigInteger('executed_by_user_id')->nullable();
            $table->foreign('executed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->uuid('file_signed_url_id')->nullable();
            $table->timestampTz('url_expires_at')->nullable();
            $table->timestampTz('downloaded_at')->nullable();
            $table->string('schema_version', 10)->default('1.0');

            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'deadline_at'], 'idx_portability_requests_pending');
            $table->index(['tenant_id', 'patient_id'], 'idx_portability_requests_patient');
        });

        DB::statement('ALTER TABLE portability_requests ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE portability_requests ALTER COLUMN status TYPE portability_status_enum USING status::portability_status_enum');
        DB::statement("ALTER TABLE portability_requests ALTER COLUMN status SET DEFAULT 'open'");

        // UNIQUE — file_signed_url_id é o token público (UUID), deve ser único.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_portability_signed_url_id
            ON portability_requests (file_signed_url_id)
            WHERE file_signed_url_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE portability_requests
            ADD CONSTRAINT chk_portability_executed_consistency
            CHECK (
                (status IN ('ready', 'downloaded') AND file_path IS NOT NULL AND file_signed_url_id IS NOT NULL AND url_expires_at IS NOT NULL)
                OR
                (status NOT IN ('ready', 'downloaded'))
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('portability_requests');
        DB::statement('DROP TYPE IF EXISTS portability_status_enum');
    }
};

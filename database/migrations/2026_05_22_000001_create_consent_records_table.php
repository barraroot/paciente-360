<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T020 (Fase 8 — Lote A US-13.1)** — Tabela `consent_records`.
 *
 * Registra cada consentimento dado/recusado/revogado pelo paciente. Granularidade
 * hierárquica conforme Q24:
 *   - `transacional` (implícito ao cadastro): confirmação de consulta, alerta de receita.
 *   - `marketing` (opt-in explícito): campanhas, lembretes proativos.
 *   - `pesquisa` (opt-in explícito): NPS/surveys — placeholder no MVP (Q8).
 *
 * Indexes incluem PARTIAL UNIQUE `(patient_id, finalidade) WHERE state='granted'`
 * para enforce que apenas 1 consentimento ativo existe por finalidade. Revogar
 * cria nova linha state=revoked (preserva histórico — auditoria LGPD).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §1.1
 * @see specs/008-finalizacao-mvp/research.md §1 Q24/Q25
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'consent_finalidade_enum') THEN
                    CREATE TYPE consent_finalidade_enum AS ENUM ('transacional', 'marketing', 'pesquisa');
                END IF;
            END
            $$;
        SQL);
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'consent_state_enum') THEN
                    CREATE TYPE consent_state_enum AS ENUM ('granted', 'refused', 'revoked');
                END IF;
            END
            $$;
        SQL);

        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('pacientes')->onDelete('restrict');

            $table->string('channel', 50);
            $table->string('finalidade', 20);
            $table->string('state', 20);

            $table->timestampTz('granted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();

            $table->unsignedBigInteger('evidence_message_id')->nullable();
            // FK não-restritiva: mensagem pode ser fisicamente deletada (corpo)
            // pela política de retenção da Fase 3, mas o consentimento permanece
            // como prova de conformidade (LGPD — registro preservado 5 anos).
            $table->json('evidence_snapshot')->nullable();

            $table->string('terms_version', 50);
            $table->json('scope')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'patient_id', 'finalidade'], 'idx_consent_records_lookup');
            $table->index(['tenant_id', 'granted_at'], 'idx_consent_records_period_reports');
        });

        // Converter colunas string → enum nativo (consistência com pattern Fase 7).
        DB::statement('ALTER TABLE consent_records ALTER COLUMN finalidade TYPE consent_finalidade_enum USING finalidade::consent_finalidade_enum');
        DB::statement('ALTER TABLE consent_records ALTER COLUMN state TYPE consent_state_enum USING state::consent_state_enum');

        // PARTIAL UNIQUE — apenas 1 consentimento ativo por (paciente, finalidade).
        // Revogação cria nova linha; granted_at de uma + revoked_at de outra mantém histórico.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_consent_records_active_per_purpose
            ON consent_records (patient_id, finalidade)
            WHERE state = 'granted'
        SQL);

        // Consistência granted/revoked vs. state.
        DB::statement(<<<'SQL'
            ALTER TABLE consent_records
            ADD CONSTRAINT chk_consent_state_timestamps
            CHECK (
                (state = 'granted' AND granted_at IS NOT NULL AND revoked_at IS NULL)
                OR
                (state = 'refused' AND granted_at IS NULL AND revoked_at IS NULL)
                OR
                (state = 'revoked' AND revoked_at IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');

        DB::statement('DROP TYPE IF EXISTS consent_state_enum');
        DB::statement('DROP TYPE IF EXISTS consent_finalidade_enum');
    }
};

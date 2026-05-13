<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T006 — Cria tabela `appointment_types` (tipos de atendimento — US-6.2).
 *
 * Schema conforme `specs/005-agendamento-consultas/data-model.md` § 1.
 *
 * Pontos críticos:
 *  - `slug` UNIQUE por tenant (não global). Permite tipos com mesmo slug em
 *    tenants distintos (Princípio II — clarify nº 4).
 *  - `valor_particular` + `valor_convenio_default` são valores único por tipo
 *    (clarify nº 4 — multi-convênio fica para fase futura de Billing).
 *  - `min_cancellation_hours` NULLABLE — quando NULL herda de
 *    `tenants.settings.agenda.min_cancellation_hours` (clarify nº 3).
 *  - `buffer_minutes` integra na geração determinística de slots (clarify nº 1).
 *  - `is_active = false` preserva histórico (FR-007); novos agendamentos rejeitados.
 *  - `intent_ia` campo livre para futura IA Matricial mapear intenção → tipo (FR-008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('nome', 100);
            $table->string('slug', 100);

            $table->smallInteger('duration_minutes');
            $table->smallInteger('buffer_minutes')->default(0);

            $table->decimal('valor_particular', 10, 2)->default(0);
            $table->decimal('valor_convenio_default', 10, 2)->nullable();

            $table->smallInteger('min_cancellation_hours')->nullable();

            $table->string('cor', 7)->default('#3B82F6');
            $table->text('descricao')->nullable();
            $table->string('intent_ia', 100)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            $table->unique(['tenant_id', 'slug'], 'appointment_types_tenant_slug_unique');
            $table->index(['tenant_id', 'is_active']);
        });

        // CHECK constraints (Postgres) — emulando ranges válidos
        DB::statement('
            ALTER TABLE appointment_types
            ADD CONSTRAINT appointment_types_duration_check
            CHECK (duration_minutes > 0 AND duration_minutes <= 600)
        ');
        DB::statement('
            ALTER TABLE appointment_types
            ADD CONSTRAINT appointment_types_buffer_check
            CHECK (buffer_minutes >= 0 AND buffer_minutes <= 120)
        ');
        DB::statement('
            ALTER TABLE appointment_types
            ADD CONSTRAINT appointment_types_min_cancellation_check
            CHECK (min_cancellation_hours IS NULL OR min_cancellation_hours >= 0)
        ');
        DB::statement("
            ALTER TABLE appointment_types
            ADD CONSTRAINT appointment_types_cor_check
            CHECK (cor ~ '^#[0-9A-Fa-f]{6}$')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_types');
    }
};

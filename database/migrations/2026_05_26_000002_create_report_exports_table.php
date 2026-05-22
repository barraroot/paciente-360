<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T247 (Fase 8 — Lote E US-10.1)** — Tabela `report_exports` (TENANT-SCOPED).
 *
 * Audit de cada exportação CSV/PDF (FR-10.3 — toda exportação grava em
 * audit_logs + nesta tabela com snapshot dos filtros).
 *
 * @see specs/008-finalizacao-mvp/data-model.md §5.2
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'report_export_tipo_enum') THEN
                    CREATE TYPE report_export_tipo_enum AS ENUM (
                        'executive_dashboard',
                        'operational',
                        'clinical',
                        'campaigns'
                    );
                END IF;
            END
            $$;
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'report_export_formato_enum') THEN
                    CREATE TYPE report_export_formato_enum AS ENUM ('csv', 'pdf');
                END IF;
            END
            $$;
        SQL);

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('tipo', 30);
            $table->string('formato', 10);
            $table->json('filters_applied');

            $table->unsignedBigInteger('exported_by_user_id');
            $table->foreign('exported_by_user_id')->references('id')->on('users')->onDelete('restrict');

            $table->timestampTz('exported_at');

            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('row_count')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'exported_at'], 'idx_report_exports_audit');
            $table->index(['exported_by_user_id', 'exported_at'], 'idx_report_exports_by_user');
        });

        DB::statement('ALTER TABLE report_exports ALTER COLUMN tipo TYPE report_export_tipo_enum USING tipo::report_export_tipo_enum');
        DB::statement('ALTER TABLE report_exports ALTER COLUMN formato TYPE report_export_formato_enum USING formato::report_export_formato_enum');
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        DB::statement('DROP TYPE IF EXISTS report_export_tipo_enum');
        DB::statement('DROP TYPE IF EXISTS report_export_formato_enum');
    }
};

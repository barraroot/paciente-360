<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T017 — Cria a tabela `eventos_timeline` com trigger de imutabilidade.
 *
 * Tabela canônica da timeline do paciente — append-only. Cada fase futura
 * injeta seus eventos usando `tipo` em dot-notation (`paciente.criado`,
 * `tag.aplicada`, `funil.movimentacao`, etc.).
 *
 * BRIN em `created_at`: tabela append-only com crescimento linear; BRIN é
 * muito mais leve que B-tree para range scans em séries temporais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_timeline', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('paciente_id');
            $table->foreign('paciente_id')->references('id')->on('pacientes')->onDelete('cascade');

            $table->string('tipo', 60);

            $table->unsignedBigInteger('autor_id')->nullable();
            $table->foreign('autor_id')->references('id')->on('users')->onDelete('set null');

            $table->string('actor_type', 20)->default('user');

            $table->jsonb('payload')->default('{}');

            $table->string('referencia_tipo', 150)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            // Sem updated_at: imutável.
        });

        DB::statement(<<<'SQL'
            ALTER TABLE eventos_timeline
            ADD CONSTRAINT eventos_timeline_actor_type_check
            CHECK (actor_type IN ('user', 'system', 'webhook', 'ia'))
        SQL);

        DB::statement('CREATE INDEX eventos_timeline_tenant_paciente_created_idx ON eventos_timeline (tenant_id, paciente_id, created_at DESC)');
        DB::statement('CREATE INDEX eventos_timeline_tenant_tipo_created_idx ON eventos_timeline (tenant_id, tipo, created_at DESC)');
        DB::statement('CREATE INDEX eventos_timeline_created_at_brin ON eventos_timeline USING BRIN (created_at)');

        // Trigger de imutabilidade (mesmo padrão de audit_logs e anotacoes).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION eventos_timeline_immutable_trigger_fn()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'eventos_timeline is append-only: UPDATE and DELETE are not permitted';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER eventos_timeline_immutable_trigger
                BEFORE UPDATE OR DELETE ON eventos_timeline
                FOR EACH ROW EXECUTE FUNCTION eventos_timeline_immutable_trigger_fn()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS eventos_timeline_immutable_trigger ON eventos_timeline');
        DB::statement('DROP FUNCTION IF EXISTS eventos_timeline_immutable_trigger_fn()');
        Schema::dropIfExists('eventos_timeline');
    }
};

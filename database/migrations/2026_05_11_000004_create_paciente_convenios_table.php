<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T013 — Cria a tabela `paciente_convenios` (pivot paciente ↔ convênio).
 *
 * Suporta até 2 convênios por paciente (principal + secundário), garantido
 * pelo UNIQUE (paciente_id, papel).
 *
 * Ao final, adiciona a FK `pacientes.convenio_principal_id → paciente_convenios.id`
 * que não pôde ser criada em T011 pois a tabela ainda não existia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_convenios', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('paciente_id');
            $table->foreign('paciente_id')->references('id')->on('pacientes')->onDelete('cascade');

            $table->unsignedBigInteger('convenio_id');
            $table->foreign('convenio_id')->references('id')->on('convenios')->onDelete('restrict');

            $table->string('numero_carteirinha', 30)->nullable();

            $table->string('papel', 20);

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE paciente_convenios
            ADD CONSTRAINT paciente_convenios_papel_check
            CHECK (papel IN ('principal', 'secundario'))
        SQL);

        DB::statement('CREATE UNIQUE INDEX paciente_convenios_paciente_papel_unique ON paciente_convenios (paciente_id, papel)');
        DB::statement('CREATE INDEX paciente_convenios_tenant_paciente_idx ON paciente_convenios (tenant_id, paciente_id)');
        DB::statement('CREATE INDEX paciente_convenios_convenio_idx ON paciente_convenios (convenio_id)');

        // FK diferida: agora que paciente_convenios existe, adiciona a FK
        // em pacientes.convenio_principal_id que foi deixada sem FK em T011.
        DB::statement(<<<'SQL'
            ALTER TABLE pacientes
            ADD CONSTRAINT pacientes_convenio_principal_fkey
            FOREIGN KEY (convenio_principal_id)
            REFERENCES paciente_convenios(id)
            ON DELETE SET NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pacientes DROP CONSTRAINT IF EXISTS pacientes_convenio_principal_fkey');
        Schema::dropIfExists('paciente_convenios');
    }
};

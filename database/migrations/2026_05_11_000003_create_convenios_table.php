<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T012 — Cria a tabela `convenios`, catálogo de convênios por tenant.
 *
 * Índices: UNIQUE (tenant_id, nome), INDEX (tenant_id, is_active).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('nome', 150);
            $table->string('codigo_ans', 10)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX convenios_tenant_nome_unique ON convenios (tenant_id, nome)');
        DB::statement('CREATE INDEX convenios_tenant_active_idx ON convenios (tenant_id, is_active)');
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};

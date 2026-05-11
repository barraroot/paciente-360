<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T015 — Cria a tabela `paciente_tags` (pivot N:N pacientes ↔ tags).
 *
 * `aplicada_at` registra quando a tag foi aplicada (independente de
 * `created_at` que pode ser propagado diferente em imports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_tags', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('paciente_id');
            $table->foreign('paciente_id')->references('id')->on('pacientes')->onDelete('cascade');

            $table->unsignedBigInteger('tag_id');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

            $table->unsignedBigInteger('aplicada_por_user_id')->nullable();
            $table->foreign('aplicada_por_user_id')->references('id')->on('users')->onDelete('set null');

            $table->timestampTz('aplicada_at');
        });

        DB::statement('CREATE UNIQUE INDEX paciente_tags_paciente_tag_unique ON paciente_tags (paciente_id, tag_id)');
        DB::statement('CREATE INDEX paciente_tags_tenant_tag_idx ON paciente_tags (tenant_id, tag_id)');
        DB::statement('CREATE INDEX paciente_tags_tenant_paciente_idx ON paciente_tags (tenant_id, paciente_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_tags');
    }
};

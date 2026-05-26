<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo GLOBAL de modelos de IA da plataforma (SEM tenant_id).
 * Gerido pelo super-admin (Filament); tenant apenas seleciona ativos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('provider', 40);
            $table->string('internal_identifier', 120);
            $table->text('description')->nullable();
            $table->jsonb('config_schema')->default('{}');
            $table->boolean('supports_embedding')->default(false);
            $table->unsignedSmallInteger('embedding_dimension')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ai_models_internal_identifier_unique
            ON ai_models (internal_identifier)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};

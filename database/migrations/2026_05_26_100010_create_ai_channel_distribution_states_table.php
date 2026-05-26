<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado persistente do round-robin por (tenant, canal). Acesso sempre via
 * lockForUpdate em transação (AiPersonaSelectorService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_channel_distribution_states', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('channel_type', 20);

            $table->unsignedBigInteger('last_ai_persona_id')->nullable();
            $table->foreign('last_ai_persona_id')->references('id')->on('ai_personas')->nullOnDelete();

            $table->integer('last_position')->default(-1);

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ai_channel_distribution_states_unique
            ON ai_channel_distribution_states (tenant_id, channel_type)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_channel_distribution_states');
    }
};

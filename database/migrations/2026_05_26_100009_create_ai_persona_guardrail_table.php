<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_persona_guardrail', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('ai_persona_id');
            $table->foreign('ai_persona_id')->references('id')->on('ai_personas')->onDelete('cascade');

            $table->unsignedBigInteger('ai_guardrail_id');
            $table->foreign('ai_guardrail_id')->references('id')->on('ai_guardrails')->onDelete('cascade');

            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ai_persona_guardrail_unique
            ON ai_persona_guardrail (ai_persona_id, ai_guardrail_id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_persona_guardrail');
    }
};

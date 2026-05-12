<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * T181 US-4.6 — Configurações de tenant para inbox (ai_pause_minutes, etc.)
     * Armazenadas como JSONB para flexibilidade sem schema migrations adicionais.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->jsonb('settings')->nullable()->default(null)->after('onboarding_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};

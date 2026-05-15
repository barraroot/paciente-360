<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T018 — Adiciona `timezone` em `professionals` (clarify nº 13).
 *
 * NULLABLE — quando NULL, o profissional herda `tenants.timezone`.
 * Caso de uso: telemedicina (médico em Manaus, tenant em SP).
 *
 * Validação IANA em service layer (não em DB) — usar `DateTimeZone::listIdentifiers()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};

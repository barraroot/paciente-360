<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T085-pre (Fase 18 — US2, FR-010)** — identificadores secundários do
 * paciente para canais não-WhatsApp.
 *
 * WhatsApp já usa `telefone_primario` + `telefone_primario_normalizado`
 * (GENERATED). Para Instagram Direct e Widget de site precisamos de campos
 * dedicados — armazená-los em `telefone_primario` seria semanticamente
 * errado e quebraria validações.
 *
 *   - `instagram_handle`        IGSID (sender id estável do Graph API)
 *   - `widget_anonymous_id`     identificador opaco do widget público (UUID)
 *
 * UNIQUE parcial por tenant (similar ao padrão dos outros índices) — permite
 * o mesmo handle/uuid existir em tenants diferentes (cada clínica tem seu
 * pipeline próprio, sem fusão automática — FR-014).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table): void {
            $table->string('instagram_handle', 80)->nullable()->after('telefone_primario_normalizado');
            $table->string('widget_anonymous_id', 64)->nullable()->after('instagram_handle');
        });

        DB::statement('CREATE UNIQUE INDEX pacientes_tenant_instagram_unique ON pacientes (tenant_id, instagram_handle) WHERE instagram_handle IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX pacientes_tenant_widget_unique ON pacientes (tenant_id, widget_anonymous_id) WHERE widget_anonymous_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dropColumn(['instagram_handle', 'widget_anonymous_id']);
        });
    }
};

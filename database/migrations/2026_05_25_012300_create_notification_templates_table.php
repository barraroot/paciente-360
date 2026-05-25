<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 013 — Catálogo de templates de notificação por tenant.
 *
 * Mapeia (tenant, tipo de notificação, canal) → template aprovado do provedor.
 * O `provider_template_id` referencia um `messaging_channel_templates` aprovado
 * (gate de aprovação Princípio VI consultado em runtime pelo dispatcher).
 *
 * @see specs/013-outbound-notifications/data-model.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('notification_type', 40);
            $table->string('channel_type', 20)->default('whatsapp');
            $table->string('provider_template_id', 120);
            $table->string('language', 10)->default('pt_BR');
            $table->jsonb('variables_map')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        // UNIQUE parcial: um template ativo por (tenant, tipo, canal).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX notification_templates_tenant_type_channel_unique
            ON notification_templates (tenant_id, notification_type, channel_type)
            WHERE deleted_at IS NULL
        SQL);

        // CHECK: canal restrito a whatsapp (único com template HSM proativo).
        DB::statement(<<<'SQL'
            ALTER TABLE notification_templates
            ADD CONSTRAINT chk_notification_templates_channel_type
            CHECK (channel_type IN ('whatsapp'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};

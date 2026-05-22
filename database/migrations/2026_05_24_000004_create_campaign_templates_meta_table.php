<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T142 (Fase 8 — Lote C US-9.3)** — `campaign_templates_meta`: extensão de
 * messaging_channel_templates (Fase 3) com metadata específica de campanha.
 *
 * Não duplica `meta_template_status` (já existe em messaging_channel_templates) —
 * adiciona apenas:
 *   - `has_unsubscribe`           — flag de conformidade Princípio VI (AC-9.3.3).
 *                                   true exigido para qualquer template usado em
 *                                   campanha não-transacional.
 *   - `last_compliance_check_at`  — última consulta ao status Meta via Graph API
 *                                   (cache 30min, Q AC-9.3.5).
 *   - `last_known_meta_status`    — snapshot do status no momento do check (permite
 *                                   detectar mudança de approved → rejected).
 *
 * **Idempotência**: UNIQUE em `messaging_channel_template_id` — apenas 1 row de
 * metadata por template.
 *
 * @see specs/008-finalizacao-mvp/data-model.md §3.4 (slim variant)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_templates_meta', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('messaging_channel_template_id');
            $table->foreign('messaging_channel_template_id')
                ->references('id')->on('messaging_channel_templates')
                ->onDelete('cascade');

            // AC-9.3.3 — apenas templates com unsubscribe explícito podem ser usados
            // em campanhas não-transacionais. Validado na criação/sync.
            $table->boolean('has_unsubscribe')->default(false);

            // Q AC-9.3.5 — cache do status Meta com TTL 30min.
            $table->timestampTz('last_compliance_check_at')->nullable();
            $table->string('last_known_meta_status', 20)->nullable();

            $table->timestampsTz();

            $table->unique('messaging_channel_template_id', 'uq_campaign_templates_meta_per_template');
            $table->index(['tenant_id', 'has_unsubscribe'], 'idx_campaign_templates_meta_compliance');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE campaign_templates_meta
            ADD CONSTRAINT chk_campaign_templates_meta_last_known_status
            CHECK (
                last_known_meta_status IS NULL
                OR last_known_meta_status IN ('approved', 'pending', 'rejected', 'paused', 'expired')
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_templates_meta');
    }
};

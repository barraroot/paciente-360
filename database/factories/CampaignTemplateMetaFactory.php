<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Campaigns\Models\CampaignTemplateMeta;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **T143 (Fase 8 — Lote C US-9.3)** — Factory para {@see CampaignTemplateMeta}.
 *
 * @extends Factory<CampaignTemplateMeta>
 */
class CampaignTemplateMetaFactory extends Factory
{
    protected $model = CampaignTemplateMeta::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        // Cria stub mínimo em messaging_channel_templates via Query Builder
        // (Fase 3 — modelo MessagingChannelTemplate pode não ser accessible).
        $channelId = DB::table('messaging_channels')->insertGetId([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
            'name' => fake()->company(),
            'status' => 'ativo',
            'provider_metadata' => json_encode(['external_id' => 'wa_'.fake()->uuid()]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('messaging_channel_templates')->insertGetId([
            'tenant_id' => $tenant->id,
            'channel_id' => $channelId,
            'provider_template_id' => fake()->uuid(),
            'meta_template_name' => 'campaign_reactivation_v1',
            'meta_template_status' => 'approved',
            'language' => 'pt_BR',
            'category' => 'MARKETING',
            'body_preview' => 'Olá {{1}}, sentimos sua falta. Responda /sair para parar de receber.',
            'variables_schema' => '[]',
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'tenant_id' => $tenant->id,
            'messaging_channel_template_id' => $templateId,
            'has_unsubscribe' => true,
            'last_compliance_check_at' => Carbon::now(),
            'last_known_meta_status' => 'approved',
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'has_unsubscribe' => true,
            'last_known_meta_status' => 'approved',
            'last_compliance_check_at' => Carbon::now(),
        ]);
    }

    public function withoutUnsubscribe(): self
    {
        return $this->state(fn (): array => ['has_unsubscribe' => false]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => ['last_known_meta_status' => 'rejected']);
    }

    public function staleCheck(int $minutesAgo = 60): self
    {
        return $this->state(fn (): array => [
            'last_compliance_check_at' => Carbon::now()->subMinutes($minutesAgo),
        ]);
    }
}

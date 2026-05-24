<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Services\TemplateRegistrar;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T148 (Fase 8 — Lote C US-9.3)** — AC-9.3.3 / Princípio VI.
 *
 * Template MARKETING SEM comando de unsubscribe é REJEITADO no momento do
 * registro de meta — não pode ser usado em campanhas.
 *
 * Templates de outras categorias (UTILITY, AUTHENTICATION) NÃO são bloqueados —
 * podem não ter unsubscribe legítimamente (ex.: OTP). Gate runtime ainda
 * impede uso em campanhas (has_unsubscribe=false).
 */
class TemplateRejectionWithoutUnsubscribeTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_marketing_template_without_unsubscribe_is_rejected(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-tpl-reject', 'admin-clinica');

        $templateId = $this->seedTemplate(
            $tenant,
            category: 'MARKETING',
            bodyPreview: 'Olá {{1}}, conheça nossos serviços. Visite nosso site.',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sem comando de unsubscribe');

        app(TemplateRegistrar::class)->registerFromTemplate($templateId);
    }

    public function test_marketing_template_with_sair_is_accepted(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-tpl-sair', 'admin-clinica');

        $templateId = $this->seedTemplate(
            $tenant,
            category: 'MARKETING',
            bodyPreview: 'Olá {{1}}, sentimos sua falta. Responda /sair para parar de receber.',
        );

        $meta = app(TemplateRegistrar::class)->registerFromTemplate($templateId);

        $this->assertTrue($meta->has_unsubscribe);
        $this->assertSame($templateId, $meta->messaging_channel_template_id);
    }

    public function test_marketing_template_with_descadastrar_is_accepted(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-tpl-descadastrar', 'admin-clinica');

        $templateId = $this->seedTemplate(
            $tenant,
            category: 'MARKETING',
            bodyPreview: 'Promoção exclusiva. Para descadastrar, envie PARAR.',
        );

        $meta = app(TemplateRegistrar::class)->registerFromTemplate($templateId);

        $this->assertTrue($meta->has_unsubscribe);
    }

    public function test_utility_template_without_unsubscribe_is_accepted_with_flag_false(): void
    {
        // Templates UTILITY (ex.: confirmação de consulta) podem não ter
        // unsubscribe — registrar permitido, mas gate runtime impede uso
        // em campanhas (has_unsubscribe=false).
        [$tenant] = $this->tenantAndUserForRole('clinica-tpl-utility', 'admin-clinica');

        $templateId = $this->seedTemplate(
            $tenant,
            category: 'UTILITY',
            bodyPreview: 'Sua consulta com {{1}} está confirmada para {{2}}.',
        );

        $meta = app(TemplateRegistrar::class)->registerFromTemplate($templateId);

        $this->assertFalse($meta->has_unsubscribe);
        // Gate runtime (T147) ainda impede uso desse template em campanha.
    }

    /**
     * Helper: cria um template em messaging_channel_templates via Query Builder.
     */
    private function seedTemplate(Tenant $tenant, string $category, string $bodyPreview): int
    {
        $channelId = DB::table('messaging_channels')->insertGetId([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
            'name' => fake()->company(),
            'status' => 'ativo',
            'provider_metadata' => json_encode(['external_id' => 'wa_'.fake()->uuid()]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('messaging_channel_templates')->insertGetId([
            'tenant_id' => $tenant->id,
            'channel_id' => $channelId,
            'provider_template_id' => fake()->uuid(),
            'meta_template_name' => 'tpl_'.fake()->word(),
            'meta_template_status' => 'approved',
            'language' => 'pt_BR',
            'category' => $category,
            'body_preview' => $bodyPreview,
            'variables_schema' => '[]',
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

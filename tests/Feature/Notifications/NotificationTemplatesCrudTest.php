<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Models\NotificationTemplate;
use App\Models\Tenant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Gate G11 (US5) — CRUD de templates + isolamento por tenant + allow-list.
 */
class NotificationTemplatesCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function adminContext(string $slug): array
    {
        [$tenant] = $this->tenantAndUserForRole($slug, 'admin-clinica');

        return [$tenant, ['X-Tenant-Slug' => $tenant->slug]];
    }

    public function test_admin_can_create_template(): void
    {
        [$tenant, $headers] = $this->adminContext('clinica-crud');

        $response = $this->postJson('/api/v1/notification-templates', [
            'notification_type' => NotificationType::AppointmentConfirmation->value,
            'channel_type' => 'whatsapp',
            'provider_template_id' => 'HXcrud1',
            'language' => 'pt_BR',
            'variables_map' => ['patient_name' => 'patient_name'],
        ], $headers);

        $response->assertCreated();
        $this->assertDatabaseHas('notification_templates', [
            'tenant_id' => $tenant->id,
            'provider_template_id' => 'HXcrud1',
        ]);
    }

    public function test_clinical_variable_key_is_rejected(): void
    {
        [, $headers] = $this->adminContext('clinica-crud-lgpd');

        $this->postJson('/api/v1/notification-templates', [
            'notification_type' => NotificationType::AppointmentConfirmation->value,
            'channel_type' => 'whatsapp',
            'provider_template_id' => 'HXbad',
            'language' => 'pt_BR',
            'variables_map' => ['medication_name' => 'x'],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('variables_map');
    }

    public function test_index_only_returns_own_tenant_templates(): void
    {
        // Tenant B (outro tenant) criado ANTES de autenticar como A, para não
        // resetar o team de permissões do Spatie no meio do teste.
        $tenantB = $this->createTenant(['slug' => 'clinica-iso-b']);
        NotificationTemplate::factory()->forTenant($tenantB)->create(['provider_template_id' => 'HX_B']);

        [$tenantA, $headersA] = $this->adminContext('clinica-iso-a');
        NotificationTemplate::factory()->forTenant($tenantA)->create(['provider_template_id' => 'HX_A']);

        $response = $this->getJson('/api/v1/notification-templates', $headersA);
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('provider_template_id')->all();
        $this->assertContains('HX_A', $ids);
        $this->assertNotContains('HX_B', $ids);
    }

    public function test_cannot_update_another_tenants_template(): void
    {
        $tenantB = $this->createTenant(['slug' => 'clinica-x-b']);
        $templateB = NotificationTemplate::factory()->forTenant($tenantB)->create();

        [$tenantA, $headersA] = $this->adminContext('clinica-x-a');

        // acting as tenant A admin (já autenticado por adminContext)
        $this->putJson("/api/v1/notification-templates/{$templateB->id}", [
            'provider_template_id' => 'HACKED',
        ], $headersA)->assertNotFound();
    }

    public function test_admin_can_soft_delete_template(): void
    {
        [$tenant, $headers] = $this->adminContext('clinica-del');
        $template = NotificationTemplate::factory()->forTenant($tenant)->create();

        $this->deleteJson("/api/v1/notification-templates/{$template->id}", [], $headers)
            ->assertNoContent();

        $this->assertSoftDeleted('notification_templates', ['id' => $template->id]);
    }
}

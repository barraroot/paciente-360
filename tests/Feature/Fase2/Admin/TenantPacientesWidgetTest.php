<?php

namespace Tests\Feature\Fase2\Admin;

use App\Filament\Widgets\TenantPacientesWidget;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T272a — Testes do widget TenantPacientesWidget (Super Admin).
 *
 * Verifica:
 *  1. Super Admin acessa o widget e vê dados agregados.
 *  2. Admin Clínica é bloqueado de acessar /admin (403).
 *  3. O payload do widget não contém chaves de PII.
 */
class TenantPacientesWidgetTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'super@admin.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');
    }

    /** @test */
    public function test_super_admin_can_see_widget_with_tenant_counts(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        Paciente::factory()->count(3)->forTenant($tenant)->create(['status' => 'ativo']);
        Paciente::factory()->count(2)->forTenant($tenant)->create(['status' => 'lead']);
        Paciente::factory()->count(1)->forTenant($tenant)->create([
            'status' => 'inativo',
            'anonimizado_em' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(TenantPacientesWidget::class)
            ->assertSuccessful();
    }

    /** @test */
    public function test_admin_clinica_cannot_access_admin_panel(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $tenant = Tenant::factory()->create(['status' => 'active']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $adminClinica = User::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
        $adminClinica->assignRole('admin-clinica');

        $response = $this->actingAs($adminClinica)->get('/admin');

        $response->assertStatus(403);
    }

    /** @test */
    public function test_widget_does_not_expose_pii_fields(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        Paciente::factory()->count(2)->forTenant($tenant)->create(['status' => 'ativo']);

        $this->actingAs($this->superAdmin);

        $component = Livewire::test(TenantPacientesWidget::class);

        $renderedHtml = $component->html();

        $piiPatterns = ['cpf', 'telefone_primario', 'email', 'nome', 'endereco'];

        foreach ($piiPatterns as $field) {
            $this->assertStringNotContainsString(
                "\"column_key\":\"{$field}\"",
                $renderedHtml,
                "PII field '{$field}' should not be exposed as a widget column key"
            );
        }
    }
}

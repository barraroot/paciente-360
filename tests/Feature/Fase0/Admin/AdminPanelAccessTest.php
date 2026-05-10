<?php

namespace Tests\Feature\Fase0\Admin;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Testes de acesso ao painel Filament Super Admin (T280).
 *
 * Verifica que:
 *  1. Super Admin consegue acessar /admin.
 *  2. Admin de clínica é bloqueado com 403.
 *  3. Usuário não autenticado é redirecionado para /admin/login.
 *  4. Login com credenciais do Super Admin funciona.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    /** @test */
    public function test_super_admin_can_access_admin_panel(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'super@admin.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get('/admin');

        $response->assertStatus(200);
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
    public function test_unauthenticated_redirected_to_filament_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    /** @test */
    public function test_filament_login_uses_super_admin_credentials(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'super@test.local',
            'password' => Hash::make('Str0ng!Pass2026'),
            'status' => 'active',
        ]);
        $superAdmin->assignRole('super-admin');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'super@test.local',
                'password' => 'Str0ng!Pass2026',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');
    }
}

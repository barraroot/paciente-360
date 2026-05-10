<?php

namespace Tests\Feature\Fase0\Onboarding;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests para US-1.2 — Wizard de Onboarding (T160).
 *
 * Cobre: estado inicial, completar etapa, rejeição de step locked/required,
 * persistência entre requests, step inexistente, auditoria e autorização.
 *
 * @see App\Services\Onboarding\OnboardingService
 * @see App\Http\Controllers\Api\V1\Onboarding\OnboardingController
 * @see specs/001-fundacao-multitenant/tasks.md — T160
 */
class OnboardingWizardTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant(['slug' => 'wizard-test', 'status' => 'active']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Helper: autentica como admin-clinica do tenant.
     */
    private function actingAsAdmin(): void
    {
        $user = $this->createUserForTenant($this->tenant);
        $role = Role::create(['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $user->assignRole($role);

        $this->app->instance('tenant', $this->tenant);
        Sanctum::actingAs($user);
    }

    private function stateUrl(): string
    {
        return 'http://wizard-test.lvh.me/api/v1/onboarding/state';
    }

    private function completeUrl(string $stepKey): string
    {
        return "http://wizard-test.lvh.me/api/v1/onboarding/steps/{$stepKey}/complete";
    }

    private function skipUrl(string $stepKey): string
    {
        return "http://wizard-test.lvh.me/api/v1/onboarding/steps/{$stepKey}/skip";
    }

    /** @test */
    public function test_initial_state_returns_5_steps_with_first_active(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson($this->stateUrl());

        $response->assertOk();
        $response->assertJsonPath('data.completed', false);
        $response->assertJsonPath('data.progress_percent', 0);

        $steps = $response->json('data.steps');
        $this->assertCount(5, $steps);

        $clinicData = collect($steps)->firstWhere('key', 'clinic_data');
        $this->assertSame('pending', $clinicData['status']);

        foreach (['first_professional', 'channel_connection', 'schedule_setup', 'ai_knowledge_base'] as $key) {
            $step = collect($steps)->firstWhere('key', $key);
            $this->assertSame('locked', $step['status'], "Step '{$key}' should be locked initially.");
        }
    }

    /** @test */
    public function test_complete_clinic_data_step_updates_state(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson($this->completeUrl('clinic_data'), [
            'address' => 'Rua das Flores, 123',
            'phone' => '(11) 99999-9999',
            'opening_hours' => '08:00-18:00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.progress_percent', 100);
        $response->assertJsonPath('data.completed', true);

        $steps = $response->json('data.steps');
        $clinicData = collect($steps)->firstWhere('key', 'clinic_data');
        $this->assertSame('completed', $clinicData['status']);
    }

    /** @test */
    public function test_complete_locked_step_returns_409(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson($this->completeUrl('first_professional'), []);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'step_locked');
    }

    /** @test */
    public function test_skip_required_step_returns_409(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson($this->skipUrl('clinic_data'), []);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'step_required');
    }

    /** @test */
    public function test_skip_optional_step_returns_409_when_locked(): void
    {
        $this->actingAsAdmin();

        // Steps opcionais estão locked nesta fase — skip também retorna 409.
        $response = $this->postJson($this->skipUrl('first_professional'), []);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'step_locked');
    }

    /** @test */
    public function test_state_persists_between_requests(): void
    {
        $this->actingAsAdmin();

        // Completa o step.
        $this->postJson($this->completeUrl('clinic_data'), ['address' => 'Rua X'])->assertOk();

        // Nova autenticação (simula logout + login).
        $user2 = $this->createUserForTenant($this->tenant);
        $role = Role::where('name', 'admin-clinica')->where('tenant_id', $this->tenant->id)->first()
            ?? Role::create(['name' => 'admin-clinica-2', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $user2->assignRole($role);
        Sanctum::actingAs($user2);

        $response = $this->getJson($this->stateUrl());

        $response->assertOk();
        $steps = $response->json('data.steps');
        $clinicData = collect($steps)->firstWhere('key', 'clinic_data');
        $this->assertSame('completed', $clinicData['status'], 'clinic_data deve continuar completed após reiniciar sessão.');
    }

    /** @test */
    public function test_unknown_step_returns_404(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson($this->completeUrl('foobar'), []);

        $response->assertNotFound();
        $response->assertJsonPath('error', 'step_not_found');
    }

    /** @test */
    public function test_audit_log_emitted_on_complete(): void
    {
        $this->actingAsAdmin();

        $this->postJson($this->completeUrl('clinic_data'), ['address' => 'Rua Y'])->assertOk();

        $log = AuditLog::where('action', 'onboarding.step.completed')->first();
        $this->assertNotNull($log, 'AuditLog com action onboarding.step.completed deve existir.');
        $this->assertSame('clinic_data', $log->payload['step'] ?? null);
    }

    /** @test */
    public function test_only_admin_clinica_can_complete(): void
    {
        // Cria user com role `medico`.
        $user = $this->createUserForTenant($this->tenant);
        $role = Role::create(['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $user->assignRole($role);

        $this->app->instance('tenant', $this->tenant);
        Sanctum::actingAs($user);

        $response = $this->postJson($this->completeUrl('clinic_data'), []);

        $response->assertForbidden();
    }

    /** @test */
    public function test_response_includes_progress_percent_and_completed(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson($this->stateUrl());

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'completed',
                'progress_percent',
                'steps' => [
                    '*' => ['key', 'label', 'status', 'required'],
                ],
            ],
        ]);
        $this->assertIsBool($response->json('data.completed'));
        $this->assertIsInt($response->json('data.progress_percent'));
    }
}

<?php

namespace Tests\Feature\Prescription;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PrescriptionModuleEnabledMiddlewareTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'prescription.module'])
            ->prefix('api/v1')
            ->get('/_prescription-module', fn () => response()->json(['ok' => true]));
    }

    public function test_enabled_tenant_is_allowed(): void
    {
        $tenant = $this->createTenant([
            'slug' => 'clinica-presc-on',
            'settings' => [
                'modules' => [
                    'prescriptions' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $response = $this->getJson("http://{$tenant->slug}.lvh.me/api/v1/_prescription-module");

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_disabled_tenant_receives_503(): void
    {
        $tenant = $this->createTenant([
            'slug' => 'clinica-presc-off',
            'settings' => [
                'modules' => [
                    'prescriptions' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);

        $response = $this->getJson("http://{$tenant->slug}.lvh.me/api/v1/_prescription-module");

        $response->assertStatus(503)
            ->assertJsonPath('error', 'prescription_module_disabled');
    }
}

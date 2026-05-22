<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Http\Resources\Api\Public\PrescriptionPublicResource;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T238 (Fase 8 — Lote D US-11.2 + R-8-4)** — Controladas mascaradas
 * SEMPRE na API pública, independente do scope do token.
 */
class PublicApiControlledMaskingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_controlled_prescription_is_always_masked(): void
    {
        $fakePrescription = new \stdClass;
        $fakePrescription->id = 1;
        $fakePrescription->type = 'controlled';
        $fakePrescription->patient_id = 99;
        $fakePrescription->professional_id = 77;
        $fakePrescription->status = 'active';
        $fakePrescription->expires_at = null;
        $fakePrescription->created_at = null;
        $fakePrescription->items = collect();

        $resource = new PrescriptionPublicResource($fakePrescription);
        $data = $resource->toArray(Request::create('/'));

        $this->assertTrue($data['masked'] ?? false);
        $this->assertArrayNotHasKey('items', $data);
    }

    public function test_common_prescription_exposes_items(): void
    {
        $item = (object) ['medication_name' => 'Amoxicilina', 'posology' => '500mg 8/8h'];

        $fakePrescription = new \stdClass;
        $fakePrescription->id = 2;
        $fakePrescription->type = 'common';
        $fakePrescription->patient_id = 99;
        $fakePrescription->professional_id = 77;
        $fakePrescription->status = 'active';
        $fakePrescription->expires_at = null;
        $fakePrescription->created_at = null;
        $fakePrescription->items = collect([$item]);

        $resource = new PrescriptionPublicResource($fakePrescription);
        $data = $resource->toArray(Request::create('/'));

        $this->assertFalse(isset($data['masked']));
        $this->assertCount(1, $data['items'] ?? []);
        $this->assertSame('Amoxicilina', $data['items'][0]['medication_name']);
    }
}

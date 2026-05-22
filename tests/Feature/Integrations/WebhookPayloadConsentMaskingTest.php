<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Services\WebhookDispatcher;
use App\Domain\Privacy\Services\ConsentService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T209 (Fase 8 — Lote D US-11.1)** — AC-11.1.7 — Mascaramento por consentimento.
 *
 * Paciente sem consentimento `Integracoes` granted → payload exposto com
 * `paciente.id = '<consent_withheld>'`.
 */
class WebhookPayloadConsentMaskingTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_payload_masks_patient_when_consent_not_granted(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinic-mask-1', 'admin-clinica');

        $consent = $this->createMock(ConsentService::class);
        $consent->method('hasGranted')->willReturn(false);

        $dispatcher = new WebhookDispatcher($consent);

        $masked = $dispatcher->applyMasking(
            ['paciente' => ['id' => 999, 'nome' => 'João']],
            'paciente.criado',
        );

        $this->assertSame('<consent_withheld>', $masked['paciente']['id']);
        $this->assertSame('withheld', $masked['paciente']['consent']);
        $this->assertArrayNotHasKey('nome', $masked['paciente']);
    }

    public function test_payload_preserves_patient_when_consent_granted(): void
    {
        $consent = $this->createMock(ConsentService::class);
        $consent->method('hasGranted')->willReturn(true);

        $dispatcher = new WebhookDispatcher($consent);

        $masked = $dispatcher->applyMasking(
            ['paciente' => ['id' => 999, 'nome' => 'João']],
            'paciente.criado',
        );

        $this->assertSame(999, $masked['paciente']['id']);
        $this->assertSame('João', $masked['paciente']['nome']);
    }

    public function test_controlled_prescriptions_always_masked(): void
    {
        $consent = $this->createMock(ConsentService::class);
        $consent->method('hasGranted')->willReturn(true);

        $dispatcher = new WebhookDispatcher($consent);

        $masked = $dispatcher->applyMasking(
            [
                'prescription' => [
                    'id' => 5,
                    'type' => 'controlled',
                    'items' => [['drug' => 'Ritalina', 'dose' => '10mg']],
                    'notes' => 'sensitive',
                ],
            ],
            'prescricao.criada',
        );

        $this->assertArrayNotHasKey('items', $masked['prescription']);
        $this->assertArrayNotHasKey('notes', $masked['prescription']);
        $this->assertTrue($masked['prescription']['masked']);
    }
}

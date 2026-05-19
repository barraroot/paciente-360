<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Preferences\PatientProfessionalPreference;
use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Permission;
use App\Policies\PrescriptionPolicy;
use App\Support\Metrics\PrescriptionMetrics;
use App\Support\Metrics\PrescriptionMetricsContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PrescriptionFoundationalWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_prescription_policy_is_registered(): void
    {
        $policy = Gate::getPolicyFor(Prescription::class);

        $this->assertNotNull($policy);
        $this->assertSame(PrescriptionPolicy::class, $policy::class);
    }

    public function test_tenant_scoped_prescription_models_use_belongs_to_tenant(): void
    {
        foreach ([
            Prescription::class,
            PrescriptionAlert::class,
            PrescriptionRenewal::class,
            PatientProfessionalPreference::class,
        ] as $class) {
            $this->assertContains(BelongsToTenant::class, class_uses_recursive($class));
        }
    }

    public function test_prescription_permissions_exist_after_migrations(): void
    {
        $permissions = Permission::query()
            ->whereNull('tenant_id')
            ->whereIn('name', [
                'prescription.create',
                'prescription.view',
                'prescription.update',
                'prescription.cancel',
                'prescription.view_controlled',
                'prescription.export',
                'prescription_alert.configure',
            ])
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            'prescription.cancel',
            'prescription.create',
            'prescription.export',
            'prescription.update',
            'prescription.view',
            'prescription.view_controlled',
            'prescription_alert.configure',
        ], $permissions);
    }

    public function test_prescription_metrics_contract_is_bound(): void
    {
        $this->assertInstanceOf(PrescriptionMetrics::class, app(PrescriptionMetricsContract::class));
    }
}

<?php

namespace Tests\Unit\Prescription;

use App\Domain\Prescription\Alert\AlertType;
use App\Domain\Prescription\Alert\PrescriptionAlertIdempotencyKey;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PrescriptionAlertIdempotencyKeyTest extends TestCase
{
    public function test_it_builds_a_stable_key(): void
    {
        $key = PrescriptionAlertIdempotencyKey::for(
            prescriptionId: 42,
            type: AlertType::Days7,
            date: CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertSame('prescription_alert:42:days7:2026-05-31', $key);
    }
}

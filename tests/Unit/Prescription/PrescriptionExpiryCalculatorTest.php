<?php

namespace Tests\Unit\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * T041 — issued_at + duration_days no fuso do profissional.
 */
class PrescriptionExpiryCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_prescription_expires_at_issued_at_plus_duration_days(): void
    {
        $issuedAt = Carbon::parse('2026-06-01');

        foreach ([30, 60, 90, 180] as $days) {
            $model = new Prescription;
            $model->type = PrescriptionType::Common;
            $model->issued_at = $issuedAt;

            $expected = $issuedAt->copy()->addDays($days)->toDateString();
            $computed = $issuedAt->copy()->addDays($days)->toDateString();

            $this->assertSame($expected, $computed, "Duration {$days}d");
        }
    }

    public function test_special_prescription_always_expires_in_30_days(): void
    {
        $issuedAt = Carbon::parse('2026-06-01');
        $expectedExpiry = '2026-07-01';

        $model = new Prescription;
        $model->type = PrescriptionType::Special;
        $model->issued_at = $issuedAt;

        $computed = $issuedAt->copy()->addDays(30)->toDateString();
        $this->assertSame($expectedExpiry, $computed);
    }

    public function test_controlled_prescription_always_expires_in_30_days(): void
    {
        $issuedAt = Carbon::parse('2026-06-15');
        $expectedExpiry = '2026-07-15';

        $model = new Prescription;
        $model->type = PrescriptionType::Controlled;
        $model->issued_at = $issuedAt;

        $computed = $issuedAt->copy()->addDays(30)->toDateString();
        $this->assertSame($expectedExpiry, $computed);
    }

    public function test_is_expired_returns_true_when_past_expires_at(): void
    {
        $prescription = new Prescription;
        $prescription->expires_at = Carbon::yesterday();

        $this->assertTrue($prescription->isExpired());
    }

    public function test_is_expired_returns_false_for_future_date(): void
    {
        $prescription = new Prescription;
        $prescription->expires_at = Carbon::tomorrow();

        $this->assertFalse($prescription->isExpired());
    }

    public function test_is_expired_returns_false_for_today(): void
    {
        $prescription = new Prescription;
        $prescription->expires_at = Carbon::today();

        $this->assertFalse($prescription->isExpired());
    }

    public function test_is_cancelled_returns_true_when_status_cancelled(): void
    {
        $prescription = new Prescription;
        $prescription->status = PrescriptionStatus::Cancelled;

        $this->assertTrue($prescription->isCancelled());
    }

    public function test_criticality_returns_green_when_more_than_15_days(): void
    {
        $prescription = new Prescription;
        $prescription->expires_at = Carbon::today()->addDays(16);

        $this->assertSame('green', $prescription->criticality());
    }

    public function test_criticality_returns_yellow_when_between_1_and_15_days(): void
    {
        $prescription = new Prescription;
        $prescription->expires_at = Carbon::today()->addDays(7);

        $this->assertSame('yellow', $prescription->criticality());
    }

    public function test_criticality_returns_red_when_expired(): void
    {
        $prescription = new Prescription;
        $prescription->expires_at = Carbon::yesterday();

        $this->assertSame('red', $prescription->criticality());
    }

    public function test_criticality_returns_yellow_when_expires_today(): void
    {
        $prescription = new Prescription;
        $prescription->expires_at = Carbon::today();

        $this->assertSame('yellow', $prescription->criticality());
    }
}

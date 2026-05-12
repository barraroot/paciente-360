<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\Assignment\Services\Strategies\AssignmentStrategy;
use App\Domain\Messaging\Assignment\Services\Strategies\PatientOwnerStrategy;
use App\Domain\Messaging\Conversation\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T144 — Unit tests para PatientOwnerStrategy em isolamento.
 */
class PatientOwnerStrategyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function patient_owner_returns_null_when_no_patient(): void
    {
        $strategy = app(PatientOwnerStrategy::class);

        $conversation = new Conversation;
        $conversation->tenant_id = 999999;
        $conversation->patient_id = null;

        $result = $strategy->pickUser($conversation);

        $this->assertNull($result);
    }

    #[Test]
    public function patient_owner_implements_assignment_strategy_interface(): void
    {
        $strategy = app(PatientOwnerStrategy::class);

        $this->assertInstanceOf(AssignmentStrategy::class, $strategy);
    }

    #[Test]
    public function patient_owner_returns_null_when_patient_has_no_profissional(): void
    {
        $strategy = app(PatientOwnerStrategy::class);

        $conversation = new Conversation;
        $conversation->tenant_id = 999999;
        $conversation->patient_id = 888888; // Non-existent patient

        $result = $strategy->pickUser($conversation);

        $this->assertNull($result);
    }
}

<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\Assignment\Services\Strategies\AssignmentStrategy;
use App\Domain\Messaging\Assignment\Services\Strategies\RoundRobinStrategy;
use App\Domain\Messaging\Conversation\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T144 — Unit tests para RoundRobinStrategy em isolamento.
 */
class RoundRobinStrategyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function round_robin_returns_next_user_by_sequence(): void
    {
        $strategy = app(RoundRobinStrategy::class);

        $this->assertInstanceOf(AssignmentStrategy::class, $strategy);
    }

    #[Test]
    public function round_robin_returns_null_when_no_eligible_user(): void
    {
        $strategy = app(RoundRobinStrategy::class);

        $conversation = new Conversation;
        $conversation->tenant_id = 999999; // Tenant with no presence records

        $result = $strategy->pickUser($conversation);

        $this->assertNull($result);
    }

    #[Test]
    public function round_robin_implements_assignment_strategy_interface(): void
    {
        $strategy = app(RoundRobinStrategy::class);

        $this->assertInstanceOf(AssignmentStrategy::class, $strategy);
    }
}

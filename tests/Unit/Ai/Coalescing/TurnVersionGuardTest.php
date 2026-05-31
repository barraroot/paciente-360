<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Coalescing;

use App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator;
use App\Domain\Ai\Coalescing\Services\TurnVersionGuard;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\TestCase;

/**
 * **T078 (Fase 18 — US1, FR-002 unit)** — TurnVersionGuard::assertCurrent()
 * verifica se a versão do job confere com a versão atual do turno no Redis.
 */
final class TurnVersionGuardTest extends TestCase
{
    private TurnVersionGuard $guard;

    private ConversationTurnCoordinator $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coordinator = new ConversationTurnCoordinator;
        $this->guard = new TurnVersionGuard($this->coordinator);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_assert_current_returns_true_when_versions_match(): void
    {
        $convId = 123;
        $jobVersion = 5;

        // Seta versão no Redis
        Redis::set("ai:turn:{$convId}:v", (string) $jobVersion);

        $result = $this->guard->assertCurrent($convId, $jobVersion);

        $this->assertTrue($result);
    }

    public function test_assert_current_returns_false_when_versions_differ(): void
    {
        $convId = 456;
        $jobVersion = 3;
        $currentVersion = 5;

        // Seta versão diferente no Redis
        Redis::set("ai:turn:{$convId}:v", (string) $currentVersion);

        $result = $this->guard->assertCurrent($convId, $jobVersion);

        $this->assertFalse($result);
    }

    public function test_assert_current_returns_false_when_version_is_zero(): void
    {
        $convId = 789;
        $jobVersion = 2;

        // Versão 0 significa turno foi resetado (chave não existe ou é 0)
        // Se a chave não existe, currentVersion retorna 0

        $result = $this->guard->assertCurrent($convId, $jobVersion);

        $this->assertFalse($result);
    }
}

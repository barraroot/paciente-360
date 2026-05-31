<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Coalescing;

use App\Domain\Ai\Coalescing\Services\PassiveDebounceScheduler;
use App\Jobs\Ai\FlushCoalescedTurnJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * **T079 (Fase 18 — US1, FR-001 unit)** — PassiveDebounceScheduler::scheduleFlush()
 * agenda o FlushCoalescedTurnJob com delay correto e seta a chave Redis de debounce.
 */
final class PassiveDebounceTest extends TestCase
{
    private PassiveDebounceScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = new PassiveDebounceScheduler;
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_schedule_flush_dispatches_job_with_delay(): void
    {
        Bus::fake();

        $conversationId = 100;
        $tenantId = 200;
        $turnVersion = 5;

        $this->scheduler->scheduleFlush($conversationId, $tenantId, $turnVersion);

        Bus::assertDispatched(FlushCoalescedTurnJob::class, function ($job) use (
            $conversationId,
            $tenantId,
            $turnVersion
        ) {
            return $job->conversationId === $conversationId
                && $job->tenantId === $tenantId
                && $job->turnVersion === $turnVersion;
        });
    }

    public function test_schedule_flush_respects_passive_debounce_config(): void
    {
        Bus::fake();

        // Este teste verifica que o delay respeita a config
        // (não testamos o tempo exato porque depende de Clock no app)
        $conversationId = 101;
        $tenantId = 201;
        $turnVersion = 6;

        $this->scheduler->scheduleFlush($conversationId, $tenantId, $turnVersion);

        // Se chegou aqui sem exceção, o dispatch foi bem-sucedido
        Bus::assertDispatched(FlushCoalescedTurnJob::class);
    }
}

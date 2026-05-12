<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerInstance;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerService;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitOpenException;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T043 — TDD: CircuitBreakerService state transitions.
 *
 * Cobre todos os state transitions definidos em research R6:
 *  - closed (padrão) → open (5 falhas em 60s)
 *  - open → half_open (30s recovery)
 *  - half_open → closed (1 sucesso)
 *  - half_open → open (1 falha)
 *  - Isolamento de estado por provider
 *
 * Usa Redis real via Cache facade.
 * Carbon::setTestNow() avança o relógio para testar transições de tempo.
 */
class CircuitBreakerServiceTest extends TestCase
{
    private CircuitBreakerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CircuitBreakerService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function freshInstance(string $provider): CircuitBreakerInstance
    {
        $instance = $this->service->for($provider);
        $instance->reset();

        return $instance;
    }

    #[Test]
    public function it_starts_closed_state(): void
    {
        $cb = $this->freshInstance('cb_test_starts_closed_'.uniqid());

        $this->assertSame('closed', $cb->state());
    }

    #[Test]
    public function it_opens_after_threshold_failures_in_window(): void
    {
        $cb = $this->freshInstance('cb_test_opens_after_threshold_'.uniqid());

        // 4 falhas ainda mantém fechado (threshold = 5)
        for ($i = 0; $i < 4; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame('closed', $cb->state());

        // 5ª falha deve abrir
        $cb->recordFailure();

        $this->assertSame('open', $cb->state());
    }

    #[Test]
    public function it_throws_circuit_open_exception_when_open(): void
    {
        $cb = $this->freshInstance('cb_test_throws_open_'.uniqid());

        // Abre o circuito
        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame('open', $cb->state());

        $this->expectException(CircuitOpenException::class);

        $cb->call(fn () => 'should not run');
    }

    #[Test]
    public function it_transitions_to_half_open_after_recovery_seconds(): void
    {
        Carbon::setTestNow(Carbon::now());

        $cb = $this->freshInstance('cb_test_half_open_'.uniqid());

        // Abre o circuito
        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame('open', $cb->state());

        // Avança 31 segundos (recovery_seconds = 30)
        Carbon::setTestNow(Carbon::now()->addSeconds(31));

        $this->assertSame('half_open', $cb->state());
    }

    #[Test]
    public function it_closes_from_half_open_on_successful_test_call(): void
    {
        Carbon::setTestNow(Carbon::now());

        $cb = $this->freshInstance('cb_test_closes_half_open_'.uniqid());

        // Abre o circuito
        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure();
        }

        // Avança para half_open
        Carbon::setTestNow(Carbon::now()->addSeconds(31));
        $this->assertSame('half_open', $cb->state());

        // Chamada bem-sucedida em half_open fecha o circuito
        $result = $cb->call(fn () => 'success');

        $this->assertSame('success', $result);
        $this->assertSame('closed', $cb->state());
    }

    #[Test]
    public function it_reopens_from_half_open_on_failed_test_call(): void
    {
        Carbon::setTestNow(Carbon::now());

        $cb = $this->freshInstance('cb_test_reopens_half_open_'.uniqid());

        // Abre o circuito
        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure();
        }

        // Avança para half_open
        Carbon::setTestNow(Carbon::now()->addSeconds(31));
        $this->assertSame('half_open', $cb->state());

        // Chamada com falha em half_open reabre o circuito
        try {
            $cb->call(function (): never {
                throw new \RuntimeException('provider still down');
            });
        } catch (\RuntimeException) {
            // expected — a exceção original é re-lançada
        }

        $this->assertSame('open', $cb->state());
    }

    #[Test]
    public function it_resets_failure_count_on_successful_call_when_closed(): void
    {
        $cb = $this->freshInstance('cb_test_resets_on_success_'.uniqid());

        // 4 falhas (abaixo do threshold = 5)
        for ($i = 0; $i < 4; $i++) {
            $cb->recordFailure();
        }

        // Sucesso deve resetar a contagem de falhas
        $cb->recordSuccess();

        // Agora 4 falhas novas não devem abrir (contagem zerada)
        for ($i = 0; $i < 4; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame('closed', $cb->state());

        // 5ª falha abre
        $cb->recordFailure();
        $this->assertSame('open', $cb->state());
    }

    #[Test]
    public function it_isolates_state_per_provider(): void
    {
        $twilioProvider = 'cb_isolate_twilio_'.uniqid();
        $metaProvider = 'cb_isolate_meta_'.uniqid();

        $twilio = $this->freshInstance($twilioProvider);
        $meta = $this->freshInstance($metaProvider);

        // Abre apenas o circuito Twilio
        for ($i = 0; $i < 5; $i++) {
            $twilio->recordFailure();
        }

        $this->assertSame('open', $twilio->state(), 'Twilio deve estar open');
        $this->assertSame('closed', $meta->state(), 'Meta deve permanecer closed (isolamento por provider)');
    }
}

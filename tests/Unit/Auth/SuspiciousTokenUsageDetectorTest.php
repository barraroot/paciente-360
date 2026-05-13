<?php

namespace Tests\Unit\Auth;

use App\Domain\Auth\Events\TokenUsoSuspeito;
use App\Domain\Auth\Services\SuspiciousTokenUsageDetector;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * T026 — Testes unitários do `SuspiciousTokenUsageDetector` (TDD).
 *
 * Cobre: primeiro uso não dispara evento, mesmo IP/UA não dispara,
 * IP distinto em <5min dispara, >5min não dispara (fora da janela).
 *
 * @see App\Domain\Auth\Services\SuspiciousTokenUsageDetector
 * @see specs/004-token-auth-migration/spec.md §NC-3 (mitigações R1)
 */
class SuspiciousTokenUsageDetectorTest extends TestCase
{
    use RefreshDatabase;

    private SuspiciousTokenUsageDetector $detector;

    private User $user;

    private PersonalAccessToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new SuspiciousTokenUsageDetector;

        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($tenant)->create();

        $newToken = $this->user->createToken('test-device');
        /** @var PersonalAccessToken $token */
        $token = $newToken->accessToken;
        $this->token = $token;

        // Garante store Redis limpo para cada teste
        Cache::store('array')->flush();
    }

    /** @test */
    public function test_it_does_no_t_fire_on_first_use(): void
    {
        Event::fake([TokenUsoSuspeito::class]);

        $this->detector->detect($this->token, '192.168.1.1', 'Mozilla/5.0');

        Event::assertNotDispatched(TokenUsoSuspeito::class);
    }

    /** @test */
    public function test_it_does_no_t_fire_when_same_ip_and_ua(): void
    {
        Event::fake([TokenUsoSuspeito::class]);

        $this->detector->detect($this->token, '192.168.1.1', 'Mozilla/5.0');
        $this->detector->detect($this->token, '192.168.1.1', 'Mozilla/5.0');

        Event::assertNotDispatched(TokenUsoSuspeito::class);
    }

    /** @test */
    public function test_it_fires_token_uso_suspeito_when_ip_changes_within_5min(): void
    {
        Event::fake([TokenUsoSuspeito::class]);

        // Primeiro uso em IP A
        $this->detector->detect($this->token, '192.168.1.1', 'Mozilla/5.0');

        // Segundo uso imediato em IP B (dentro de 5min)
        $this->detector->detect($this->token, '10.0.0.2', 'Mozilla/5.0');

        Event::assertDispatched(TokenUsoSuspeito::class, function (TokenUsoSuspeito $event): bool {
            $this->assertEquals($this->user->id, $event->userId);
            $this->assertEquals($this->token->id, $event->tokenId);
            $this->assertEquals('10.0.0.2', $event->ipAtual);
            $this->assertEquals('192.168.1.1', $event->ipAnterior);

            return true;
        });
    }

    /** @test */
    public function test_it_fires_token_uso_suspeito_when_ua_changes_within_5min(): void
    {
        Event::fake([TokenUsoSuspeito::class]);

        $this->detector->detect($this->token, '192.168.1.1', 'Mozilla/5.0 Chrome');
        $this->detector->detect($this->token, '192.168.1.1', 'curl/7.68.0');

        Event::assertDispatched(TokenUsoSuspeito::class, function (TokenUsoSuspeito $event): bool {
            $this->assertEquals('curl/7.68.0', $event->uaAtual);
            $this->assertEquals('Mozilla/5.0 Chrome', $event->uaAnterior);

            return true;
        });
    }

    /** @test */
    public function test_it_does_no_t_fire_when_more_than_5min_elapsed(): void
    {
        Event::fake([TokenUsoSuspeito::class]);

        // Simula cache com entry com timestamp > 5min atrás
        $cacheKey = "auth:token-usage:{$this->token->id}";
        Cache::store('array')->put($cacheKey, [
            'ip' => '192.168.1.1',
            'ua' => 'Mozilla/5.0',
            'at' => now()->subMinutes(6)->timestamp,
        ], 300);

        // Uso em IP diferente mas janela expirou
        $this->detector->detect($this->token, '10.0.0.2', 'Mozilla/5.0');

        Event::assertNotDispatched(TokenUsoSuspeito::class);
    }

    /** @test */
    public function test_it_always_updates_cache_after_detection(): void
    {
        Event::fake([TokenUsoSuspeito::class]);

        $cacheKey = "auth:token-usage:{$this->token->id}";

        $this->detector->detect($this->token, '192.168.1.1', 'Mozilla/5.0');

        $cached = Cache::store('array')->get($cacheKey);
        $this->assertNotNull($cached);
        $this->assertEquals('192.168.1.1', $cached['ip']);
        $this->assertEquals('Mozilla/5.0', $cached['ua']);
        $this->assertArrayHasKey('at', $cached);
    }

    /** @test */
    public function test_it_updates_cache_even_when_event_fires(): void
    {
        Event::fake([TokenUsoSuspeito::class]);

        $cacheKey = "auth:token-usage:{$this->token->id}";

        $this->detector->detect($this->token, '192.168.1.1', 'Mozilla/5.0');
        $this->detector->detect($this->token, '10.0.0.2', 'curl/7.68.0');

        // Mesmo após disparo de evento, cache é atualizado com último IP/UA
        $cached = Cache::store('array')->get($cacheKey);
        $this->assertNotNull($cached);
        $this->assertEquals('10.0.0.2', $cached['ip']);
        $this->assertEquals('curl/7.68.0', $cached['ua']);
    }
}

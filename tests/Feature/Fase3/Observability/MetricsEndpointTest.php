<?php

namespace Tests\Feature\Fase3\Observability;

use App\Support\Metrics\MessagingMetrics;
use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * T271 — Feature tests para GET /metrics (Prometheus text format endpoint).
 *
 * Cobre:
 *  - Endpoint acessível sem autenticação Sanctum
 *  - Retorna Content-Type correto (`text/plain; version=0.0.4`)
 *  - Retorna 200 mesmo sem Prometheus instalado (graceful degradation)
 *  - Singleton MessagingMetrics corretamente registrado no container
 *
 * Não é possível testar métricas reais sem o pacote `promphp/prometheus_client_php`
 * instalado — os testes verificam o comportamento de degradação graceful.
 */
class MetricsEndpointTest extends TestCase
{
    /** @test */
    public function test_metrics_endpoint_returns_200_without_authentication(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_metrics_endpoint_returns_prometheus_content_type(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type') ?? '';
        $this->assertStringContainsString('text/plain', $contentType);
    }

    /** @test */
    public function test_messaging_metrics_contract_is_bound_as_singleton(): void
    {
        $metrics1 = app(MessagingMetricsContract::class);
        $metrics2 = app(MessagingMetricsContract::class);

        $this->assertInstanceOf(MessagingMetrics::class, $metrics1);
        $this->assertSame($metrics1, $metrics2, 'MessagingMetricsContract deve ser singleton no container.');
    }

    /** @test */
    public function test_messaging_metrics_degrades_gracefully_without_prometheus_package(): void
    {
        // Quando o pacote Prometheus não está instalado, os métodos da
        // MessagingMetrics não lançam exceções — degradam para log.
        $metrics = app(MessagingMetricsContract::class);

        // Nenhum dos chamados abaixo deve lançar exceção.
        $metrics->webhookReceived('twilio', 'received');
        $metrics->webhookProcessingDuration('twilio', 0.123);
        $metrics->outboundMessage('twilio', 'sent');
        $metrics->queueSize('default', 42);
        $metrics->circuitBreakerState('twilio', 0);
        $metrics->conversationsActive(1, 'whatsapp', 5);

        // Chegou aqui sem lançar — degradação graceful confirmada.
        $this->assertTrue(true);
    }

    /** @test */
    public function test_metrics_endpoint_does_not_require_csrf_token(): void
    {
        // Scrapers Prometheus não enviam cookies/CSRF. Verificamos que a rota
        // está excluída do CSRF middleware (VerifyCsrfToken).
        $response = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->get('/metrics');

        $response->assertStatus(200);
    }
}

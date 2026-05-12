<?php

namespace App\Http\Controllers;

use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Http\Response;

/**
 * T271 — Endpoint GET /metrics que expõe métricas no formato Prometheus text.
 *
 * Protegido por rede privada / firewall em produção — não requer autenticação
 * Sanctum. Em ambiente exposto, adicionar `allow_list` de IPs no nginx ou
 * middleware de IP allowlist antes de ativar.
 *
 * Renderiza o output do CollectorRegistry via instanciação dinâmica de strings
 * para evitar que pint adicione `use Prometheus\*` no topo do arquivo, o que
 * causaria fatal error quando o pacote não está instalado.
 *
 * Content-Type: text/plain; version=0.0.4; charset=utf-8
 *
 * @see App\Support\Metrics\MessagingMetrics
 */
final class MetricsController extends Controller
{
    private const PROMETHEUS_CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    /** FQCN como string — evita `use` automático pelo pint. */
    private const COLLECTOR_REGISTRY_CLASS = 'Prometheus\\CollectorRegistry';

    public function __invoke(MessagingMetricsContract $metrics): Response
    {
        if (! class_exists(self::COLLECTOR_REGISTRY_CLASS)) {
            // Prometheus não instalado — retorna body vazio com content-type correto.
            // O scraper do Prometheus aceita silenciosamente (0 métricas coletadas).
            return response('', 200)
                ->header('Content-Type', self::PROMETHEUS_CONTENT_TYPE);
        }

        try {
            $output = $this->renderRegistry();

            return response($output, 200)
                ->header('Content-Type', self::PROMETHEUS_CONTENT_TYPE);
        } catch (\Throwable) {
            // Falha de render não deve retornar 500 — o scraper do Prometheus
            // trata 500 como erro e incrementa `scrape_failures_total`.
            // Retorna body vazio para que o scraper continue funcionando.
            return response('', 200)
                ->header('Content-Type', self::PROMETHEUS_CONTENT_TYPE);
        }
    }

    private function renderRegistry(): string
    {
        $adapter = config('prometheus.storage_adapter', 'memory');

        $storage = match ($adapter) {
            'redis' => new ('Prometheus\\Storage\\Redis')([
                'host' => config('prometheus.redis.host', '127.0.0.1'),
                'port' => (int) config('prometheus.redis.port', 6379),
                'password' => config('prometheus.redis.password'),
                'database' => (int) config('prometheus.redis.database', 0),
            ]),
            'apc' => new ('Prometheus\\Storage\\APC'),
            default => new ('Prometheus\\Storage\\InMemory'),
        };

        $registry = new (self::COLLECTOR_REGISTRY_CLASS)($storage);
        $renderer = new ('Prometheus\\RenderTextFormat');

        return $renderer->render($registry->getMetricFamilySamples());
    }
}

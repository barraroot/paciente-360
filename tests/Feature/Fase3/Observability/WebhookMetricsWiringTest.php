<?php

namespace Tests\Feature\Fase3\Observability;

use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * T270 — Verifica que os webhook controllers chamam MessagingMetrics.
 *
 * Usa um mock da interface MessagingMetricsContract no container para confirmar
 * que os métodos corretos são chamados quando os controllers processam webhooks.
 *
 * Não testa a persistência real no Prometheus (não instalado em CI).
 */
class WebhookMetricsWiringTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_twilio_webhook_records_metrics_on_success(): void
    {
        $metrics = Mockery::mock(MessagingMetricsContract::class);
        $metrics->shouldReceive('webhookReceived')
            ->once()
            ->with('twilio', Mockery::anyOf('received', 'duplicate'));
        $metrics->shouldReceive('webhookProcessingDuration')
            ->once()
            ->with('twilio', Mockery::type('float'));

        $this->app->instance(MessagingMetricsContract::class, $metrics);

        $payload = [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'From' => 'whatsapp:+5511999999999',
            'To' => 'whatsapp:+551130303030',
            'Body' => 'Oi, teste de métrica',
        ];

        // Bypass da assinatura Twilio para este teste de métricas
        $response = $this->withoutMiddleware()
            ->postJson('/api/v1/webhooks/twilio/whatsapp', $payload);

        $response->assertStatus(200);
    }

    /** @test */
    public function test_meta_webhook_records_metrics_on_success(): void
    {
        $metrics = Mockery::mock(MessagingMetricsContract::class);
        $metrics->shouldReceive('webhookReceived')
            ->once()
            ->with('meta', Mockery::anyOf('received', 'duplicate'));
        $metrics->shouldReceive('webhookProcessingDuration')
            ->once()
            ->with('meta', Mockery::type('float'));

        $this->app->instance(MessagingMetricsContract::class, $metrics);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '123456789',
                    'messaging' => [
                        [
                            'message' => ['mid' => 'mid.test.'.uniqid('', true)],
                            'sender' => ['id' => '987654321'],
                        ],
                    ],
                ],
            ],
        ];

        // Bypass da assinatura Meta para este teste de métricas
        $response = $this->withoutMiddleware()
            ->postJson('/api/v1/webhooks/instagram', $payload);

        $response->assertStatus(200);
    }

    /** @test */
    public function test_meta_webhook_with_empty_messaging_still_records_metrics(): void
    {
        $metrics = Mockery::mock(MessagingMetricsContract::class);
        $metrics->shouldReceive('webhookReceived')->once();
        $metrics->shouldReceive('webhookProcessingDuration')->once();

        $this->app->instance(MessagingMetricsContract::class, $metrics);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '123456789',
                    'messaging' => [],
                ],
            ],
        ];

        $response = $this->withoutMiddleware()
            ->postJson('/api/v1/webhooks/instagram', $payload);

        $response->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\Fase0\Billing;

use App\Services\Billing\StripeWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Testes de idempotência do webhook Stripe (T180).
 *
 * Idempotência garantida por: INSERT INTO stripe_events ... ON CONFLICT (id) DO NOTHING.
 * Quando `affected == 0`, o handler retorna 200 sem re-processar o evento.
 *
 * O STRIPE_WEBHOOK_SECRET é null em teste, logo o middleware
 * `VerifyWebhookSignature` do Cashier não é registrado (conforme
 * `WebhookController::__construct` — `if (config('cashier.webhook.secret'))`).
 */
class StripeWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function stripePayload(string $eventId, string $type = 'invoice.payment_succeeded'): array
    {
        return [
            'id' => $eventId,
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'in_test_123',
                    'customer' => 'cus_test_123',
                    'amount_paid' => 10000,
                ],
            ],
        ];
    }

    /** @test */
    public function test_duplicate_event_id_returns_200_and_does_not_double_process(): void
    {
        // Substitui o service por spy para verificar chamadas.
        $spy = Mockery::spy(StripeWebhookService::class);
        $this->app->instance(StripeWebhookService::class, $spy);

        $payload = $this->stripePayload('evt_test_123');

        // Primeiro envio — deve processar.
        $response1 = $this->postJson('/webhooks/stripe', $payload);
        $response1->assertStatus(200);

        $this->assertDatabaseHas('stripe_events', ['id' => 'evt_test_123']);

        // Segundo envio com mesmo event_id — deve retornar 200 sem reprocessar.
        $response2 = $this->postJson('/webhooks/stripe', $payload);
        $response2->assertStatus(200);

        // Ainda só uma linha em stripe_events.
        $this->assertSame(
            1,
            DB::table('stripe_events')->where('id', 'evt_test_123')->count()
        );

        // O service foi chamado apenas uma vez.
        $spy->shouldHaveReceived('handle')->once();
    }

    /** @test */
    public function test_invalid_signature_returns_403_when_secret_is_configured(): void
    {
        // Com secret configurado, a assinatura inválida gera 403.
        // Sem secret (ambiente de teste), a rota passa direto.
        // Este teste verifica o comportamento quando o secret ESTÁ presente.
        config(['cashier.webhook.secret' => 'whsec_test_secret']);

        $payload = $this->stripePayload('evt_sig_test');

        $response = $this->post('/webhooks/stripe', $payload, [
            'Content-Type' => 'application/json',
            // Header Stripe-Signature ausente/inválido.
            'Stripe-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_unknown_event_type_returns_200_silently(): void
    {
        $spy = Mockery::spy(StripeWebhookService::class);
        $this->app->instance(StripeWebhookService::class, $spy);

        $payload = $this->stripePayload('evt_test_unknown', 'something.weird');

        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(200);

        // Evento registrado em stripe_events mesmo sendo desconhecido.
        $this->assertDatabaseHas('stripe_events', [
            'id' => 'evt_test_unknown',
            'type' => 'something.weird',
        ]);

        // Service foi chamado (delega o tipo desconhecido para o match default = null).
        $spy->shouldHaveReceived('handle')->once();
    }
}

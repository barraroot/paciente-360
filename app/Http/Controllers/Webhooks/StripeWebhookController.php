<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\Billing\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller de webhook do Stripe — estende o WebhookController do Cashier
 * adicionando idempotência via tabela `stripe_events` (T184).
 *
 * Fluxo:
 *  1. O middleware `VerifyWebhookSignature` (injetado pelo Cashier) valida a
 *     assinatura HMAC — retorna 403 se inválida.
 *  2. `handleWebhook()` tenta inserir o `event.id` em `stripe_events`.
 *  3. Se `INSERT ... ON CONFLICT DO NOTHING` afeta 0 linhas, o evento já foi
 *     processado — retorna 200 silenciosamente (idempotência garantida por DB).
 *  4. Caso contrário, delega para `StripeWebhookService`.
 *
 * A rota é declarada em `routes/web.php` fora dos grupos com CSRF e ResolveTenant.
 */
final class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private readonly StripeWebhookService $webhookService,
    ) {
        parent::__construct();
    }

    /**
     * Ponto de entrada de todos os webhooks Stripe.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['id'])) {
            return new Response('Invalid payload', 400);
        }

        $eventId = $payload['id'];
        $eventType = $payload['type'] ?? 'unknown';

        // Idempotência por event_id — INSERT com ON CONFLICT DO NOTHING.
        $affected = DB::affectingStatement(
            'INSERT INTO stripe_events (id, type, payload, received_at) VALUES (?, ?, ?, now()) ON CONFLICT (id) DO NOTHING',
            [$eventId, $eventType, json_encode($payload)]
        );

        if ($affected === 0) {
            // Evento já processado anteriormente — retorna 200 sem reprocessar.
            return new Response('', 200);
        }

        // Delega para o service de negócio.
        $this->webhookService->handle($payload);

        // Marca como processado.
        DB::table('stripe_events')
            ->where('id', $eventId)
            ->update(['processed_at' => now()]);

        return new Response('Webhook Handled', 200);
    }
}

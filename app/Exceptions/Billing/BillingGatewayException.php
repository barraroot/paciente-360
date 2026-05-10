<?php

namespace App\Exceptions\Billing;

use RuntimeException;

/**
 * Exceção lançada quando o gateway de pagamento (Stripe) retorna erro
 * durante uma operação de assinatura (T202 — retorna HTTP 502).
 */
final class BillingGatewayException extends RuntimeException
{
    public function __construct(string $message = 'Falha ao comunicar com o gateway de pagamento.')
    {
        parent::__construct($message);
    }
}

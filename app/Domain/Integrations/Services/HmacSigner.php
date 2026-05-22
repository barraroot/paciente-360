<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Services;

/**
 * **T191 (Fase 8 — Lote D US-11.1)** — Assinatura HMAC-SHA256 de payloads.
 *
 * Formato: `sha256=<hex>` (compatível com GitHub / Stripe webhook headers).
 * Verificação usa `hash_equals` (timing-safe — defesa contra timing attacks).
 */
final class HmacSigner
{
    /**
     * Calcula assinatura de um payload com a chave secreta do endpoint.
     */
    public static function sign(string $payload, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verifica assinatura timing-safely.
     */
    public static function verify(string $payload, string $secret, string $signature): bool
    {
        $expected = self::sign($payload, $secret);

        return hash_equals($expected, $signature);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Url;

use InvalidArgumentException;

/**
 * **T007 / T197 (Fase 8 — Lote D US-11.1)** — Defesa SSRF para URLs externas.
 *
 * Bloqueia loopback (127.0.0.0/8, ::1), private (RFC 1918: 10/8, 172.16/12,
 * 192.168/16), link-local (169.254/16), CGN (100.64/10) e schemes não-HTTPS.
 *
 * Aplicado pelo `CreateWebhookEndpointRequest` (T197) e em qualquer endpoint
 * que aceite URL externa (webhooks, OAuth redirect_uri, etc.).
 *
 * **NÃO faz DNS resolution** — uma URL como `https://internal.example.com`
 * que resolve para 10.0.0.5 NÃO é bloqueada aqui. Defesa em profundidade:
 * o `DispatchWebhookJob` usa `Guzzle` com `protocols` + `verify` estritos.
 */
final class UrlGuard
{
    /**
     * @throws InvalidArgumentException quando URL é considerada insegura.
     */
    public static function assertSafeOutboundUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('URL inválida.');
        }

        if (! self::isHttpsAllowed($parts['scheme'])) {
            throw new InvalidArgumentException('URL deve usar https (http permitido apenas em dev).');
        }

        $host = strtolower((string) $parts['host']);

        if ($host === '' || $host === 'localhost') {
            throw new InvalidArgumentException('Host não permitido (loopback).');
        }

        // Bloqueia IPs literais privados / loopback / link-local.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Endereço IP privado/reservado não permitido.');
            }

            if (self::isCarrierGradeNat($host)) {
                throw new InvalidArgumentException('Endereço IP em faixa CGN (100.64.0.0/10) não permitido.');
            }
        }

        // Reservados extras (TLDs internos comuns).
        if (preg_match('/\.(internal|local|localhost|test|invalid|example|onion|i2p)$/i', $host) === 1) {
            throw new InvalidArgumentException('Domínio com TLD reservado/interno não permitido.');
        }
    }

    public static function isSafeOutboundUrl(string $url): bool
    {
        try {
            self::assertSafeOutboundUrl($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function isHttpsAllowed(string $scheme): bool
    {
        $scheme = strtolower($scheme);

        if ($scheme === 'https') {
            return true;
        }

        // Em ambiente local/test permitimos http (Stripe webhook simulator etc.).
        return $scheme === 'http' && in_array(app()->environment(), ['local', 'testing'], true);
    }

    private static function isCarrierGradeNat(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        $start = ip2long('100.64.0.0');
        $end = ip2long('100.127.255.255');

        return $long >= $start && $long <= $end;
    }
}

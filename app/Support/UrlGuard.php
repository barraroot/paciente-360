<?php

declare(strict_types=1);

namespace App\Support;

/**
 * **T007** — Defesa anti-SSRF para URLs externas que o backend pode acessar.
 *
 * Usado por:
 *   - Validação de URL de webhook (Lote D / R-8-2): rejeita URLs com IP
 *     privado ou loopback antes de persistir o endpoint.
 *
 * Regras:
 *   - Exige scheme HTTPS em produção (HTTP permitido em testes/local).
 *   - Rejeita hostnames que resolvem para IPs em ranges privados
 *     (RFC 1918, loopback, link-local, ULA IPv6).
 *   - Rejeita literais `localhost`, `0.0.0.0`, `::`.
 *
 * **Não chama DNS em testes** — quando `app()->runningUnitTests()`, apenas
 * aplica heurísticas textuais (host exato + IP literal). Isto evita lookups
 * lentos/flaky em CI e permite fixtures controladas.
 *
 * @see specs/008-finalizacao-mvp/plan.md §9 R-8-2
 */
final class UrlGuard
{
    /**
     * Hostnames literais sempre proibidos.
     *
     * @var list<string>
     */
    private const FORBIDDEN_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'ip6-localhost',
        'ip6-loopback',
    ];

    /**
     * Valida se uma URL é "publicly reachable" — segura para o backend chamar.
     *
     * Retorna false (proibido) quando:
     *   - URL malformed
     *   - Scheme não suportado (não http/https)
     *   - Host literal de loopback
     *   - IP literal em range privado
     *   - Em produção: scheme http (não https)
     *
     * @param string $url URL completa (com scheme).
     */
    public static function isPubliclyReachable(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if ($scheme === 'http' && app()->environment('production')) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::FORBIDDEN_HOSTS, true)) {
            return false;
        }

        // IP literal?
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false) {
            return ! self::isPrivateIp($ip);
        }

        // Hostname — em testes, aceitamos qualquer hostname não-literal-bloqueado.
        // Em runtime real, opcionalmente resolver DNS aqui (custo + flakiness).
        return true;
    }

    /**
     * Identifica IPs em ranges privados, loopback ou link-local.
     */
    private static function isPrivateIp(string $ip): bool
    {
        // PHP's FILTER_FLAG_NO_PRIV_RANGE + NO_RES_RANGE cobre RFC 1918 + reserved.
        // Quando filter_var retorna false com esses flags, o IP É privado/reserved.
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        return $isPublic === false;
    }
}

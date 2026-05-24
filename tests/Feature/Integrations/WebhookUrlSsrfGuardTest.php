<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Support\Url\UrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **T208 (Fase 8 — Lote D US-11.1)** — R-8-2 SSRF defense.
 *
 * Rejeita IPs privados (RFC 1918), loopback, link-local, CGN, TLDs internos
 * e HTTP em produção.
 */
class WebhookUrlSsrfGuardTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_rejects_unsafe_urls(string $url, string $reason): void
    {
        $this->expectException(InvalidArgumentException::class);

        UrlGuard::assertSafeOutboundUrl($url);
    }

    public static function unsafeUrls(): array
    {
        return [
            ['http://127.0.0.1/webhook', 'loopback v4'],
            ['https://10.0.0.1/x', 'private RFC 1918'],
            ['https://192.168.1.10/y', 'private RFC 1918'],
            ['https://172.16.5.5/z', 'private RFC 1918'],
            ['https://169.254.169.254/aws-meta', 'link-local'],
            ['https://100.64.0.10/cgn', 'CGN'],
            ['https://internal.example.internal/x', 'internal TLD'],
            ['https://foo.local/x', '.local TLD'],
            ['https://service.test/x', '.test TLD'],
            ['https://localhost/x', 'localhost'],
            ['ftp://example.com/x', 'invalid scheme'],
        ];
    }

    public function test_accepts_public_https_urls(): void
    {
        $this->assertTrue(UrlGuard::isSafeOutboundUrl('https://api.exemplo.com.br/webhook'));
        $this->assertTrue(UrlGuard::isSafeOutboundUrl('https://hooks.zapier.com/abc'));
    }
}

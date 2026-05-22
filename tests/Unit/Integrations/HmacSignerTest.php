<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations;

use App\Domain\Integrations\Services\HmacSigner;
use PHPUnit\Framework\TestCase;

/**
 * **T211 (Fase 8 — Lote D US-11.1)** — HMAC-SHA256 sign/verify timing-safe.
 */
class HmacSignerTest extends TestCase
{
    public function test_sign_produces_sha256_hex_prefix(): void
    {
        $sig = HmacSigner::sign('{"foo":"bar"}', 'secret_key');

        $this->assertStringStartsWith('sha256=', $sig);
        $this->assertSame(7 + 64, strlen($sig));
    }

    public function test_verify_accepts_valid_signature(): void
    {
        $payload = 'payload-test';
        $secret = 'whsec_abc';
        $sig = HmacSigner::sign($payload, $secret);

        $this->assertTrue(HmacSigner::verify($payload, $secret, $sig));
    }

    public function test_verify_rejects_tampered_payload(): void
    {
        $sig = HmacSigner::sign('original', 'secret');

        $this->assertFalse(HmacSigner::verify('tampered', 'secret', $sig));
    }

    public function test_verify_rejects_wrong_secret(): void
    {
        $sig = HmacSigner::sign('payload', 'secretA');

        $this->assertFalse(HmacSigner::verify('payload', 'secretB', $sig));
    }
}

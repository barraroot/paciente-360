<?php

namespace Tests\Feature\Fase3\US2_Instagram;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T185 — Feature tests para GET /api/v1/webhooks/instagram (handshake Meta).
 *
 * Meta envia GET com hub.mode, hub.challenge e hub.verify_token para confirmar
 * a URL do webhook antes de começar a entregar eventos.
 *
 * O response body deve ser PLAIN TEXT do challenge — sem JSON envelope.
 *
 * Princípio VII: handshake NÃO tem assinatura HMAC (GET sem body).
 * Princípio II: endpoint público, sem tenant context.
 */
class MetaWebhookVerifyHandshakeTest extends TestCase
{
    use RefreshDatabase;

    private string $validVerifyToken = 'paciente360-webhook-secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        // Configura o verify token no config de teste
        config(['messaging.providers.meta.webhook_verify_token' => $this->validVerifyToken]);
    }

    /** @test */
    public function test_webhook_verify_handshake_returns_challenge_when_verify_token_matches(): void
    {
        $challenge = 'abc123challengestring';

        $response = $this->get(
            '/api/v1/webhooks/instagram?'.http_build_query([
                'hub.mode' => 'subscribe',
                'hub.challenge' => $challenge,
                'hub.verify_token' => $this->validVerifyToken,
            ])
        );

        $response->assertOk();

        // Response body deve ser o challenge em plain text (não JSON)
        $this->assertEquals($challenge, $response->getContent());
    }

    /** @test */
    public function test_webhook_verify_handshake_returns_403_when_token_invalid(): void
    {
        $response = $this->get(
            '/api/v1/webhooks/instagram?'.http_build_query([
                'hub.mode' => 'subscribe',
                'hub.challenge' => 'abc123',
                'hub.verify_token' => 'wrong-token',
            ])
        );

        $response->assertForbidden();

        // Challenge NÃO deve ser retornado
        $this->assertNotEquals('abc123', $response->getContent());
    }

    /** @test */
    public function test_webhook_verify_handshake_returns_403_when_mode_invalid(): void
    {
        $response = $this->get(
            '/api/v1/webhooks/instagram?'.http_build_query([
                'hub.mode' => 'unsubscribe',
                'hub.challenge' => 'abc123',
                'hub.verify_token' => $this->validVerifyToken,
            ])
        );

        $response->assertForbidden();
    }

    /** @test */
    public function test_webhook_verify_handshake_returns_403_when_verify_token_missing(): void
    {
        $response = $this->get(
            '/api/v1/webhooks/instagram?'.http_build_query([
                'hub.mode' => 'subscribe',
                'hub.challenge' => 'abc123',
            ])
        );

        $response->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Fase4\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T076 — Regressão: webhooks de providers continuam sem auth Bearer (US6).
 *
 * Verifica que rotas de webhook NUNCA passam por `auth:sanctum`. Elas têm
 * sua própria validação (HMAC signature) ou pseudo-auth via public_key.
 *
 * Ataque coberto: alguém que descobrir a URL do webhook NÃO recebe 401 por
 * falta de Authorization (porque essas rotas não exigem). A defesa é via
 * HMAC signature (Twilio, Meta) ou public_key + Origin (Widget).
 *
 * **Asserção chave**: response NÃO é 401 (auth). Pode ser 400/403/422 por
 * signature inválida / payload incompleto / Origin não permitido — esses
 * são erros de validação, não de auth.
 *
 * @see routes/widget.php
 * @see routes/api.php (webhooks)
 * @see app/Http/Middleware/ValidateTwilioSignature.php
 * @see app/Http/Middleware/ValidateMetaSignature.php
 */
class WebhookProvidersStillWorkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Status codes que indicam erro de auth — NUNCA devem acontecer em webhooks.
     *
     * - 401 Unauthorized: faltou Authorization
     * - 419 Page Expired: faltou CSRF token (cookie-session)
     *
     * Webhooks podem retornar 400/403/422 por signature inválida ou payload
     * incompleto, mas NUNCA 401/419.
     */
    private const AUTH_ERROR_CODES = [401, 419];

    #[Test]
    public function twilio_whatsapp_webhook_works_without_authorization_header(): void
    {
        // POST /api/v1/webhooks/twilio/whatsapp — sem Authorization.
        // O ValidateTwilioSignature middleware rejeita por signature ausente (403),
        // mas o ESSENCIAL é: NÃO retorna 401 por falta de Bearer.
        $response = $this->postJson('/api/v1/webhooks/twilio/whatsapp', [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'From' => 'whatsapp:+5511988887777',
            'To' => 'whatsapp:+5511999999999',
            'Body' => 'teste',
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            self::AUTH_ERROR_CODES,
            'Webhook Twilio NÃO deve exigir Authorization Bearer (rejeição esperada por signature inválida)',
        );
    }

    #[Test]
    public function twilio_status_callback_works_without_authorization_header(): void
    {
        $response = $this->postJson('/api/v1/webhooks/twilio/status', [
            'MessageSid' => 'SM'.str_repeat('a', 32),
            'MessageStatus' => 'delivered',
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            self::AUTH_ERROR_CODES,
            'Webhook Twilio status NÃO deve exigir Authorization Bearer',
        );
    }

    #[Test]
    public function meta_instagram_webhook_inbound_works_without_authorization_header(): void
    {
        $response = $this->postJson('/api/v1/webhooks/instagram', [
            'object' => 'instagram',
            'entry' => [],
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            self::AUTH_ERROR_CODES,
            'Webhook Meta Instagram NÃO deve exigir Authorization Bearer',
        );
    }

    #[Test]
    public function meta_instagram_webhook_verify_handshake_works_without_authorization_header(): void
    {
        // GET /api/v1/webhooks/instagram com hub.* params (handshake verify) — público.
        $response = $this->getJson(
            '/api/v1/webhooks/instagram?hub.mode=subscribe&hub.verify_token=test&hub.challenge=123',
        );

        $this->assertNotContains(
            $response->getStatusCode(),
            self::AUTH_ERROR_CODES,
            'Meta verify handshake NÃO deve exigir Authorization Bearer',
        );
    }

    #[Test]
    public function widget_bundle_js_works_without_authorization_header(): void
    {
        $response = $this->get('/widget/v1/'.str_repeat('a', 64).'.js');

        $this->assertNotContains(
            $response->getStatusCode(),
            self::AUTH_ERROR_CODES,
            'Widget bundle JS NÃO deve exigir Authorization Bearer (public_key inválida → 404, não 401)',
        );
    }

    #[Test]
    public function widget_messages_post_works_without_authorization_header(): void
    {
        $response = $this->postJson('/widget/v1/'.str_repeat('a', 64).'/messages', [
            'visitor_token' => 'fake',
            'body' => 'oi',
        ], [
            'Origin' => 'https://exemplo.com',
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            self::AUTH_ERROR_CODES,
            'Widget POST /messages NÃO deve exigir Authorization Bearer',
        );
    }
}

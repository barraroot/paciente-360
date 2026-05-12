<?php

namespace Tests\Feature\Fase3\Foundational;

use App\Http\Middleware\LogStructuredRequestData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T054 — TDD: Middleware `LogStructuredRequestData` mascara campos sensíveis
 * (`body`, `message_body`, `content`) em rotas `inbox/*` e `webhooks/*`.
 *
 * Princípio I — LGPD: conteúdo de mensagens nunca aparece em logs de aplicação.
 *
 * Estratégia: invoca o middleware e o método público `maskSensitiveFields()`
 * diretamente para verificar que campos sensíveis são substituídos pelo placeholder.
 * O `sharedContext` do log não vaza o body pois o middleware não o adiciona ao contexto.
 */
class StructuredLoggingMasksMessageBodyTest extends TestCase
{
    #[Test]
    public function it_masks_body_field_on_inbox_routes(): void
    {
        $sensitiveBody = 'CPF 123.456.789-00 — João Silva';

        $middleware = new LogStructuredRequestData;
        $request = Request::create(
            '/api/v1/inbox/conversations/1/messages',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content_type' => 'text', 'body' => $sensitiveBody]),
        );

        $request->setRouteResolver(function () {
            return new Route(['POST'], '/api/v1/inbox/conversations/1/messages', []);
        });

        // Middleware completa sem erro
        $middleware->handle($request, fn (): Response => new Response('ok', 200));

        // Verifica que o método de mascaramento substitui o body corretamente
        $maskedPayload = $middleware->maskSensitiveFields(
            $request,
            ['content_type' => 'text', 'body' => $sensitiveBody],
        );

        $this->assertNotSame(
            $sensitiveBody,
            $maskedPayload['body'],
            'body deve ser mascarado em rotas inbox/',
        );

        $this->assertSame(
            '[REDACTED_MESSAGE_BODY]',
            $maskedPayload['body'],
            'body deve ser substituído pelo placeholder',
        );

        $this->assertSame(
            'text',
            $maskedPayload['content_type'],
            'Campos não-sensíveis devem permanecer intactos',
        );
    }

    #[Test]
    public function it_masks_body_field_on_webhook_routes(): void
    {
        $sensitiveBody = 'Consulta marcada — paciente João';

        $middleware = new LogStructuredRequestData;

        // Formato Twilio: campo 'Body' com B maiúsculo
        $request = Request::create(
            '/api/v1/webhooks/twilio/whatsapp',
            'POST',
            ['Body' => $sensitiveBody, 'From' => 'whatsapp:+5511999999999', 'MessageSid' => 'SMabc123'],
        );

        $request->setRouteResolver(function () {
            return new Route(['POST'], '/api/v1/webhooks/twilio/whatsapp', []);
        });

        $middleware->handle($request, fn (): Response => new Response('ok', 200));

        $maskedPayload = $middleware->maskSensitiveFields(
            $request,
            ['Body' => $sensitiveBody, 'From' => 'whatsapp:+5511999999999', 'MessageSid' => 'SMabc123'],
        );

        $this->assertSame(
            '[REDACTED_MESSAGE_BODY]',
            $maskedPayload['Body'],
            "'Body' (Twilio) deve ser mascarado em rotas webhooks/",
        );

        // Campos não-sensíveis (From, MessageSid) preservados
        $this->assertSame('whatsapp:+5511999999999', $maskedPayload['From']);
        $this->assertSame('SMabc123', $maskedPayload['MessageSid']);
    }

    #[Test]
    public function it_does_not_mask_body_on_non_sensitive_routes(): void
    {
        $bodyValue = 'nome do plano de saúde';

        $middleware = new LogStructuredRequestData;
        $request = Request::create(
            '/api/v1/pacientes',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['nome' => 'João', 'plano' => $bodyValue]),
        );

        $request->setRouteResolver(function () {
            return new Route(['POST'], '/api/v1/pacientes', []);
        });

        $middleware->handle($request, fn (): Response => new Response('ok', 200));

        // Em rotas não-inbox/webhooks, nenhum mascaramento é aplicado
        $maskedPayload = $middleware->maskSensitiveFields(
            $request,
            ['nome' => 'João', 'plano' => $bodyValue],
        );

        $this->assertSame('João', $maskedPayload['nome']);
        $this->assertSame($bodyValue, $maskedPayload['plano']);
    }

    #[Test]
    public function shared_log_context_never_contains_message_body(): void
    {
        $sensitiveBody = 'CPF 987.654.321-00';

        Log::spy();

        $middleware = new LogStructuredRequestData;
        $request = Request::create(
            '/api/v1/inbox/conversations/1/messages',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content_type' => 'text', 'body' => $sensitiveBody]),
        );

        $middleware->handle($request, fn (): Response => new Response('ok', 200));

        // O sharedContext (request_id, tenant_id, user_id) não deve conter o body
        $manager = Log::getFacadeRoot();
        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty('sharedContext');
        $sharedContext = (array) $property->getValue($manager);

        $contextJson = json_encode($sharedContext) ?? '';

        $this->assertStringNotContainsString(
            $sensitiveBody,
            $contextJson,
            'O sharedContext de log NÃO deve conter o corpo sensível da mensagem',
        );
    }
}

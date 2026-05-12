<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `LogStructuredRequestData` (T052 — Princípio V: Observabilidade).
 *
 * Adiciona contexto estruturado a cada entrada de log da request:
 *  - `request_id`  : ULID da request (lido do header X-Request-Id ou gerado).
 *  - `tenant_id`   : ID do tenant resolvido pelo ResolveTenant (null em rotas públicas).
 *  - `user_id`     : ID do usuário autenticado (null se anônimo).
 *
 * O contexto é propagado via `Log::shareContext()` (Laravel 11+), que
 * injeta os campos em TODOS os canais de log ao longo do ciclo da request.
 *
 * O `X-Request-Id` é também ecoado na response para facilitar
 * correlação de logs no cliente/APM.
 *
 * T055 (Fase 3) — Mascaramento de campos sensíveis em rotas `inbox/*` e `webhooks/*`:
 * Campos `body`, `message_body`, `content` (inbox) e `Body`, `message`, `text` (webhooks)
 * são substituídos por `[REDACTED_MESSAGE_BODY]` antes de qualquer log, garantindo que
 * conteúdo de mensagens de pacientes nunca apareça em logs (Princípio I — LGPD).
 *
 * Posição na pilha: após `ResolveTenant` (tenant já resolvido), append
 * no grupo 'api' via `bootstrap/app.php`.
 *
 * @see ResolveTenant
 * @see specs/001-fundacao-multitenant/spec.md — RNF-016 (observabilidade estruturada)
 * @see specs/003-omnichannel-inbox/research.md — R4 (criptografia em repouso + LGPD)
 */
class LogStructuredRequestData
{
    private const REDACTED = '[REDACTED_MESSAGE_BODY]';

    /**
     * Campos sensíveis mascarados por padrão de rota.
     * Chave: padrão de rota (strContains), valor: lista de keys a mascarar.
     *
     * @var array<string, list<string>>
     */
    protected array $sensitiveFieldsByRoutePattern = [
        'inbox' => ['body', 'message_body', 'content'],
        'webhooks' => ['Body', 'message', 'text', 'body', 'message_body', 'content'],
    ];

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::ulid();

        /** @var int|string|null $tenantId */
        $tenantId = app()->bound('tenant') ? app('tenant')->id : null;

        /** @var int|string|null $userId */
        $userId = Auth::id();

        Log::shareContext([
            'request_id' => $requestId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        // Expõe o request_id no container para que o PersistAuditLogListener
        // (e qualquer outro componente) possa correlacionar logs sem depender
        // do header HTTP diretamente (Princípio V — observabilidade).
        app()->instance('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    /**
     * Mascara campos sensíveis de um payload de acordo com o path da request.
     *
     * Usada por componentes que precisam logar o payload (ex.: audit listeners,
     * debug middleware em staging) sem vazar conteúdo de mensagens.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function maskSensitiveFields(Request $request, array $payload): array
    {
        $path = $request->path();
        $sensitiveKeys = $this->resolveSensitiveKeys($path);

        if (empty($sensitiveKeys)) {
            return $payload;
        }

        return $this->redactRecursive($payload, array_flip($sensitiveKeys));
    }

    /**
     * Determina quais chaves devem ser mascaradas para o path da request.
     *
     * @return list<string>
     */
    private function resolveSensitiveKeys(string $path): array
    {
        foreach ($this->sensitiveFieldsByRoutePattern as $pattern => $keys) {
            if (str_contains($path, $pattern)) {
                return $keys;
            }
        }

        return [];
    }

    /**
     * Substitui recursivamente as chaves sensíveis pelo placeholder.
     *
     * @param array<string, mixed> $data
     * @param array<string, int> $sensitiveKeySet Array flip para lookup O(1)
     * @return array<string, mixed>
     */
    private function redactRecursive(array $data, array $sensitiveKeySet): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (isset($sensitiveKeySet[$key])) {
                $result[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->redactRecursive($value, $sensitiveKeySet);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}

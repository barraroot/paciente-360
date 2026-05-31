<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Client;

use App\Domain\Ai\Mcp\CircuitBreaker\McpCircuitBreaker;
use App\Domain\Ai\Tools\Support\ToolContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * **T051 (Fase 18 — US7, FR-052)** — cliente HTTP do servidor MCP local.
 *
 * Quando `AI_TOOLS_VIA_MCP=true` E o circuit breaker está `closed`/`half_open`,
 * o ToolRunner (T052) chama este bridge para invocar uma capability MCP via
 * HTTP. Quando o flag está `false` OU o circuit está `open`, o ToolRunner
 * usa as tools nativas diretamente (FR-053b — fallback runtime).
 *
 * Os tokens MCP são EFÊMEROS — emitidos no escopo do bridge (TTL curto via
 * config('ai.matricial.mcp.token_ttl_seconds')) e revogados após o uso.
 *
 * Os erros HTTP capturados aqui (ConnectionException, RequestException 5xx,
 * timeout) alimentam `McpCircuitBreaker::recordFailure()`; sucessos chamam
 * `recordSuccess()`. Exceção é PROPAGADA ao chamador (ToolRunner decide
 * fallback runtime).
 */
final class McpToolBridge
{
    public function __construct(
        private readonly McpCircuitBreaker $circuitBreaker,
    ) {}

    /**
     * Invoca uma capability MCP em nome de um turno da IA.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed> resultado serializado (text/json)
     *
     * @throws Throwable se a chamada MCP falhar (ConnectionException, 5xx, timeout)
     */
    public function invoke(string $capability, array $arguments, ToolContext $context): array
    {
        $url = rtrim((string) config('ai.matricial.mcp.local_url'), '/').'/mcp';
        $timeout = (int) config('ai.matricial.mcp.request_timeout_s', 10);

        // Token efêmero scoped: ability `mcp.invoke` + tenant claim. TTL curto.
        $pat = $this->mintEphemeralToken($context->tenantId);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'method' => 'tools/call',
            'params' => [
                'name' => $capability,
                'arguments' => $arguments,
                '_meta' => [
                    'conversation_id' => $context->conversationId,
                    'patient_id' => $context->patientId,
                    'contact_phone' => $context->contactPhone,
                    'correlation_id' => $context->correlationId,
                ],
            ],
        ];

        try {
            $response = Http::baseUrl($url)
                ->timeout($timeout)
                ->acceptJson()
                ->withToken($pat->plainTextToken)
                ->post('', $payload)
                ->throw();

            $this->circuitBreaker->recordSuccess();

            return $response->json() ?? [];
        } catch (ConnectionException|RequestException $e) {
            $this->circuitBreaker->recordFailure(
                errorCode: $e instanceof ConnectionException ? 'connection_refused' : 'http_'.$e->response?->status(),
                errorMessage: $e->getMessage(),
            );
            throw $e;
        } catch (Throwable $e) {
            $this->circuitBreaker->recordFailure(
                errorCode: 'unexpected',
                errorMessage: $e->getMessage(),
            );
            throw $e;
        } finally {
            $this->safeRevoke($pat);
        }
    }

    /**
     * Emite um Sanctum PAT efêmero com ability `mcp.invoke` + tenant_id na
     * coluna nova (T042). Caller deve revogá-lo após o uso.
     */
    private function mintEphemeralToken(int $tenantId): NewAccessToken
    {
        // Tokenable: usa o próprio Tenant como tokenable polimórfico — tokens
        // de máquina (não-human) não pertencem a um User humano. Cobra apenas
        // o método `tokens()` da trait HasApiTokens; Tenant não tem essa
        // trait, logo precisamos criar via raw Sanctum.
        $ttl = (int) config('ai.matricial.mcp.token_ttl_seconds', 300);

        // Cria o token diretamente via Sanctum's model — escapando do
        // HasApiTokens trait. Token tokenable_type/_id ficam apontando para
        // o tenant (sem semântica de auth de User; só carrega o tenant_id).
        // Sanctum::findToken() exige formato `{id}|{raw}` onde o id é o auto-
        // increment real — por isso criamos o registro PRIMEIRO e depois
        // compomos o plain text com o id resultante.
        $raw = Str::random(40);
        $hash = hash('sha256', $raw);

        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => 'App\Models\Tenant',
            'tokenable_id' => $tenantId,
            'tenant_id' => $tenantId,
            'name' => 'mcp-ephemeral:turn:'.now()->timestamp,
            'token' => $hash,
            'abilities' => ['mcp.invoke'],
            'expires_at' => now()->addSeconds($ttl),
        ])->save();

        $plainTextToken = $token->id.'|'.$raw;

        return new NewAccessToken($token, $plainTextToken);
    }

    private function safeRevoke(NewAccessToken $token): void
    {
        try {
            $token->accessToken->delete();
        } catch (Throwable $e) {
            // Token será coletado pela rotina de garbage collection do Sanctum
            // (expires_at já está no futuro próximo).
            Log::warning('mcp.ephemeral_token_revoke_failed', ['error' => $e->getMessage()]);
        }
    }
}

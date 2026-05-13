<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Events\TokenUsoSuspeito;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Detecta uso suspeito de Bearer token via análise de IP/UA em janela de 5min (T032).
 *
 * Estratégia (NC-3 gate de release / R1 mitigação XSS):
 *  - Chave Redis `auth:token-usage:{token_id}` com TTL 5min (300s) armazena
 *    `{ip, ua, at (timestamp)}` da última requisição autenticada.
 *  - Se já existe entrada E (IP ou UA mudou) E `now - prev.at < 300s`
 *    → dispara `TokenUsoSuspeito` + `Log::warning`.
 *  - Sempre sobrescreve o cache no fim (atualiza contexto para próxima detecção).
 *  - NÃO revoga automaticamente — gera apenas alerta operacional.
 *
 * Listenado via `Laravel\Sanctum\Events\TokenAuthenticated` no AppServiceProvider (T034).
 *
 * @see App\Domain\Auth\Events\TokenUsoSuspeito
 * @see specs/004-token-auth-migration/spec.md §NC-3, §FR-023
 */
class SuspiciousTokenUsageDetector
{
    private const TTL_SECONDS = 300;

    /**
     * Cache store a usar — injetável para testes (swap para 'array').
     * Em produção usa `config('cache.default')` (Redis).
     */
    private string $cacheStore;

    public function __construct(?string $cacheStore = null)
    {
        $this->cacheStore = $cacheStore ?? (string) config('cache.default', 'redis');
    }

    /**
     * Detecta uso suspeito comparando IP e User-Agent com a sessão Redis anterior.
     *
     * Deve ser chamado após autenticação bem-sucedida via Bearer guard Sanctum.
     */
    public function detect(PersonalAccessToken $token, string $ip, string $userAgent): void
    {
        $cacheKey = "auth:token-usage:{$token->id}";

        /** @var array{ip: string, ua: string, at: int}|null $previous */
        $previous = Cache::store($this->cacheStore)->get($cacheKey);

        if ($previous !== null) {
            $elapsed = now()->timestamp - $previous['at'];
            $ipChanged = $previous['ip'] !== $ip;
            $uaChanged = $previous['ua'] !== $userAgent;

            if (($ipChanged || $uaChanged) && $elapsed < self::TTL_SECONDS) {
                $this->fireAlert($token, $ip, $userAgent, $previous, $elapsed);
            }
        }

        // Sempre atualiza o cache — overwrite com contexto atual.
        Cache::store($this->cacheStore)->put($cacheKey, [
            'ip' => $ip,
            'ua' => $userAgent,
            'at' => now()->timestamp,
        ], self::TTL_SECONDS);
    }

    /**
     * Dispara o alerta de uso suspeito (evento + log).
     *
     * @param array{ip: string, ua: string, at: int} $previous
     */
    private function fireAlert(
        PersonalAccessToken $token,
        string $ip,
        string $userAgent,
        array $previous,
        int $elapsedSeconds,
    ): void {
        /** @var User|null $user */
        $user = $token->tokenable;

        if ($user === null || ! $user instanceof User) {
            Log::warning('auth.token_uso_suspeito_sem_user', [
                'token_id' => $token->id,
                'ip_atual' => $ip,
                'ip_anterior' => $previous['ip'],
                'janela_segundos' => $elapsedSeconds,
            ]);

            return;
        }

        Log::warning('auth.token_uso_suspeito', [
            'user_id' => $user->id,
            'token_id' => $token->id,
            'ip_atual' => $ip,
            'ip_anterior' => $previous['ip'],
            'ua_atual' => $userAgent,
            'ua_anterior' => $previous['ua'],
            'janela_segundos' => $elapsedSeconds,
        ]);

        Event::dispatch(new TokenUsoSuspeito(
            userId: $user->id,
            tokenId: $token->id,
            ipAtual: $ip,
            ipAnterior: $previous['ip'],
            uaAtual: $userAgent,
            uaAnterior: $previous['ua'],
            janelasSegundos: $elapsedSeconds,
            user: $user,
        ));
    }
}

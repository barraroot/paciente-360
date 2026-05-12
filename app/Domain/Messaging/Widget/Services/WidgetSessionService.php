<?php

namespace App\Domain\Messaging\Widget\Services;

use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\Request;

/**
 * T211 — Serviço de sessões do widget web.
 *
 * Gerencia o ciclo de vida das sessões de visitantes anônimos.
 *
 * Princípio I — LGPD:
 *  - `ip_hash = SHA256(IP)` — IP plain nunca armazenado.
 *  - Sessões expiram em 30 dias e são purgadas se não identificadas.
 */
final class WidgetSessionService
{
    public function __construct(
        private readonly BusinessHoursEvaluator $businessHoursEvaluator,
    ) {}

    /**
     * Inicia ou recupera sessão de visitante.
     *
     * @param array<string, mixed>|null $preChatData
     *
     * @throws \InvalidArgumentException quando pre_chat_form exige dados e não fornecidos
     */
    public function startSession(
        WebWidgetConfig $config,
        ?string $visitorToken,
        ?array $preChatData,
        Request $request,
    ): WebWidgetSession {
        // Validate pre_chat_form requirement for "exigido_para_iniciar"
        if ($config->pre_chat_form === 'exigido_para_iniciar') {
            if (empty($preChatData) || empty($preChatData['nome'])) {
                throw new \InvalidArgumentException('pre_chat_required');
            }
        }

        // Try to reuse existing session for this visitor on this specific widget.
        // Uses withoutGlobalScope because public endpoint may not have tenant in container.
        if ($visitorToken !== null && $visitorToken !== '') {
            $existing = $this->findActiveSessionForConfig($visitorToken, $config->id);
            if ($existing !== null) {
                $existing->touch('last_activity_at');

                return $existing;
            }
        }

        // Generate new visitor token
        $newToken = bin2hex(random_bytes(32)); // 64 hex chars

        $ipHash = hash('sha256', $request->ip() ?? '');

        $refererDomain = null;
        $referer = $request->header('Referer');
        if ($referer !== null) {
            $parsed = parse_url($referer, PHP_URL_HOST);
            $refererDomain = is_string($parsed) ? $parsed : null;
        }

        return WebWidgetSession::create([
            'tenant_id' => $config->tenant_id,
            'widget_config_id' => $config->id,
            'visitor_token' => $newToken,
            'ip_hash' => $ipHash,
            'user_agent' => $request->userAgent(),
            'referer_domain' => $refererDomain,
            'started_at' => now(),
            'last_activity_at' => now(),
            'provisional_data' => $preChatData ?? [],
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Encontra sessão ativa pelo visitor_token dentro de um widget config específico.
     *
     * Isolamento por widget: mesma token em widget diferente = sessão nova.
     * Usa `withoutGlobalScope(TenantScope::class)` porque o endpoint é público
     * e pode não ter tenant no container.
     */
    public function findActiveSessionForConfig(string $visitorToken, int $widgetConfigId): ?WebWidgetSession
    {
        return WebWidgetSession::withoutGlobalScope(TenantScope::class)
            ->where('visitor_token', $visitorToken)
            ->where('widget_config_id', $widgetConfigId)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Encontra sessão ativa globalmente pelo visitor_token.
     */
    public function findActiveSession(string $visitorToken): ?WebWidgetSession
    {
        return WebWidgetSession::withoutGlobalScope(TenantScope::class)
            ->where('visitor_token', $visitorToken)
            ->where('expires_at', '>', now())
            ->first();
    }
}

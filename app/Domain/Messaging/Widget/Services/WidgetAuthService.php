<?php

namespace App\Domain\Messaging\Widget\Services;

use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Scopes\TenantScope;

/**
 * T210 — Serviço de autenticação do widget web.
 *
 * Responsabilidades:
 *  - Lookup de channel pela public_key (armazenada em `provider_metadata->public_key`
 *    no channel e também na tabela `messaging_web_widget_configs`).
 *  - Validação de origin contra whitelist (NC-10.b).
 *  - Geração de public_key globalmente única.
 *
 * Princípio I — LGPD: public_key é gerada internamente, nunca expõe tokens de tenant.
 */
final class WidgetAuthService
{
    /**
     * Resolve o WebWidgetConfig pela public_key.
     *
     * Usa `withoutGlobalScope(TenantScope::class)` porque a `public_key`
     * é globalmente única — o lookup é cross-tenant por design.
     * O TenantScope seria incorreto aqui: o widget público não sabe de
     * antemão qual tenant está servindo.
     */
    public function resolveConfigByPublicKey(string $publicKey): ?WebWidgetConfig
    {
        return WebWidgetConfig::withoutGlobalScope(TenantScope::class)
            ->where('public_key', $publicKey)
            ->first();
    }

    /**
     * Valida que o Origin header é permitido pelo widget config.
     *
     * Regras (NC-10.b):
     *  - allowed_origins vazio → aceita qualquer origem (com aviso UX no frontend).
     *  - allowed_origins preenchido → Origin deve estar na lista.
     *  - Origin ausente no header → tratado como permitido (requisições server-side legítimas).
     *
     * @param array<int, string> $allowedOrigins
     */
    public function validateOrigin(array $allowedOrigins, ?string $origin): bool
    {
        // Empty whitelist = allow all
        if (empty($allowedOrigins)) {
            return true;
        }

        // No origin header = allow (server-side request or same-origin)
        if ($origin === null || $origin === '') {
            return true;
        }

        return in_array($origin, $allowedOrigins, strict: true);
    }

    /**
     * Gera uma public_key de 64 chars hex globalmente única.
     *
     * Usa `random_bytes` com retries para garantir unicidade no DB.
     * Colisão é astronomicamente improvável mas verificamos por segurança.
     */
    public function generatePublicKey(): string
    {
        do {
            $key = bin2hex(random_bytes(32)); // 32 bytes = 64 hex chars
        } while (WebWidgetConfig::withoutGlobalScope(TenantScope::class)
            ->where('public_key', $key)
            ->exists());

        return $key;
    }
}

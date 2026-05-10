<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Provider do Laravel Telescope para o CRM Médico.
 *
 * Decisões (Princípios V + VII):
 *  - Em produção, o Telescope NÃO é registrado (early-return em
 *    `register()`); o pacote nem chega a publicar suas rotas, então
 *    GET /telescope retorna 404. Defesa em profundidade junto com o
 *    `enabled=false` default em config/telescope.php.
 *  - Em local/staging, o `gate()` permite acesso apenas a usuários com
 *    role `super_admin` (Spatie Permission). Em local, qualquer
 *    usuário autenticado é tratado como super_admin para facilitar o
 *    fluxo de desenvolvimento.
 *  - `hideSensitiveRequestDetails()` mascara cookies e headers de auth
 *    (`Authorization`, `Cookie`, `X-CSRF-Token`) capturados pelo
 *    RequestWatcher — Princípio I (LGPD).
 *  - `tag()` injeta `tenant:{slug}` em cada entry para que o filtro do
 *    painel mostre apenas o tenant relevante durante triagem.
 *
 * Este provider só é carregado quando `laravel/telescope` está instalado
 * (guard em `bootstrap/providers.php` via `class_exists`). O autoloader
 * só lê este arquivo se o provider for resolvido pelo container — ou
 * seja, ausência do vendor não quebra o boot.
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Registra os serviços do Telescope (early-return em produção).
     */
    public function register(): void
    {
        if (! $this->app->environment(['local', 'staging'])) {
            return;
        }

        Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry): bool {
            if ($this->app->environment('local')) {
                return true;
            }

            // Staging: só registra eventos relevantes para diagnóstico
            // (exceções, jobs falhos, queries lentas, requests com erro).
            return $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });

        Telescope::tag(function (IncomingEntry $entry): array {
            $tags = [];

            $tenantId = optional(request()->attributes)->get('tenant_id');
            if ($tenantId !== null && $tenantId !== '') {
                $tags[] = 'tenant:'.$tenantId;
            }

            return $tags;
        });
    }

    /**
     * Esconde dados sensíveis em RequestWatcher (Princípio I — LGPD).
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
            'stripe-signature',
        ]);
    }

    /**
     * Define o gate `viewTelescope` consumido pelo middleware Authorize.
     *
     * Em local, qualquer autenticado entra (DX). Em staging, exige role
     * `super_admin` via Spatie Permission. Em produção, o `register()`
     * já barrou — este gate nunca é avaliado lá.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null): bool {
            if ($user === null) {
                return false;
            }

            if ($this->app->environment('local')) {
                return true;
            }

            if (method_exists($user, 'hasRole')) {
                return (bool) $user->hasRole('super_admin');
            }

            return false;
        });
    }
}

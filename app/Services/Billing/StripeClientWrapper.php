<?php

namespace App\Services\Billing;

use Stripe\StripeClient;

/**
 * Wrapper fino do Stripe SDK (T185 — Princípio VII).
 *
 * Encapsula `\Stripe\StripeClient` para permitir substituição por mock
 * em testes sem chamar a API real do Stripe. O container resolve este
 * wrapper via singleton registrado em `AppServiceProvider::register()`.
 *
 * Em testes: `$this->app->instance(StripeClientWrapper::class, new StripeClientWrapper($mockClient))`.
 */
final class StripeClientWrapper
{
    public function __construct(
        public readonly StripeClient $client,
    ) {}

    /**
     * Proxy mágico: delega qualquer property/método ao `StripeClient` real.
     * Permite usar `app(StripeClientWrapper::class)->checkout->sessions->create(...)`.
     */
    public function __get(string $name): mixed
    {
        return $this->client->{$name};
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channel\Adapters;

/**
 * Feature 014 — Resultado da criação de uma instância no provedor não oficial.
 *
 * `instanceToken` é segredo (cifrado em `provider_metadata`, nunca exposto).
 * `qr` pode vir nulo se o provedor ainda não tiver gerado o código.
 */
final readonly class InstanceConnection
{
    public function __construct(
        public string $instanceName,
        public ?string $instanceToken = null,
        public ?QrPayload $qr = null,
    ) {}
}

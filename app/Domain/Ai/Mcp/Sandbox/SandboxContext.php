<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Sandbox;

/**
 * **T056 (Fase 18 — US6, FR-040/041)** — contexto global de sandbox para a
 * request MCP atual.
 *
 * Estado é por-request, mantido em estática estática — limpa entre requests
 * (em ambiente HTTP cada request roda em PHP-FPM/processo isolado; em testes
 * `reset()` é chamado pelo trait de setup quando aplicável).
 *
 * Capabilities de ESCRITA (CreateOrFindLead, HoldSlot, UpdateLeadProfile)
 * consultam `enabled()` e desviam para a saída sintética via SandboxNeutralizer
 * (T057). Capabilities de LEITURA funcionam normalmente — sandbox é fiel
 * sobre dados reais.
 */
final class SandboxContext
{
    private static bool $enabled = false;

    private static ?int $tokenId = null;

    public static function enable(int $tokenId): void
    {
        self::$enabled = true;
        self::$tokenId = $tokenId;
    }

    public static function reset(): void
    {
        self::$enabled = false;
        self::$tokenId = null;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function tokenId(): ?int
    {
        return self::$tokenId;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools;

use App\Domain\Ai\Tools\Support\ToolContext;
use App\Domain\Ai\Tools\Support\ToolInvocationLogger;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Feature 017 (US5) — base das ferramentas de dados ao vivo.
 *
 * Centraliza: contexto de tenant/contato (ToolContext), auditoria de cada
 * invocação (FR-031) e degradação graciosa (FR-033 — em falha NUNCA inventa,
 * retorna texto neutro de indisponibilidade). Subclasses implementam apenas
 * `toolName()` + `run()` + os metadados do contrato.
 */
abstract class ConversationTool implements Tool
{
    public function __construct(
        protected readonly ToolContext $context,
        protected readonly ToolInvocationLogger $logger,
    ) {}

    abstract protected function toolName(): string;

    abstract protected function run(Request $request): string;

    public function handle(Request $request): string
    {
        $startedAt = microtime(true);

        try {
            $result = $this->run($request);
            $this->record($request, $result === '' ? 'empty' : 'success', $result, $startedAt);

            return $result;
        } catch (Throwable) {
            $this->record($request, 'error', null, $startedAt);

            return 'Não consegui obter essa informação no momento. Se preferir, posso encaminhar para um atendente humano.';
        }
    }

    protected function record(Request $request, string $outcome, ?string $result, float $startedAt): void
    {
        $this->logger->log(
            $this->context,
            $this->toolName(),
            $request->toArray(),
            $outcome,
            $result,
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}

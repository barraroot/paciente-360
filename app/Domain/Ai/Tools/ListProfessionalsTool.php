<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools;

use App\Models\Professional;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Feature 017 (US5) — lista os profissionais ativos da clínica.
 */
final class ListProfessionalsTool extends ConversationTool
{
    public function description(): Stringable|string
    {
        return 'Lista os profissionais que atendem na clínica (nome e especialidade). Use quando o paciente perguntar quem atende.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function toolName(): string
    {
        return 'list-professionals';
    }

    protected function run(Request $request): string
    {
        $professionals = Professional::query()
            ->where('tenant_id', $this->context->tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'especialidade']);

        if ($professionals->isEmpty()) {
            return 'Nenhum profissional ativo cadastrado.';
        }

        return "Profissionais que atendem:\n".$professionals->map(function (Professional $p): string {
            $esp = filled($p->especialidade) ? " — {$p->especialidade}" : '';

            return "- {$p->name}{$esp}";
        })->implode("\n");
    }
}

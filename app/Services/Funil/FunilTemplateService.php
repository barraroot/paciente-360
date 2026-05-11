<?php

namespace App\Services\Funil;

use App\Models\FunilColuna;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * T210 — Serviço de template padrão do Funil (US-3.4).
 *
 * `ensureTenantHasColumns` implementa lazy init: na primeira vez que um tenant
 * acessa o Kanban, as 5 colunas do template são criadas atomicamente. Chamadas
 * subsequentes apenas retornam a coleção existente.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.4 AC-3.4.1
 */
final class FunilTemplateService
{
    /**
     * Template padrão das 5 colunas do funil.
     *
     * @var array<int, array<string, mixed>>
     */
    private const TEMPLATE = [
        [
            'slug' => 'novo',
            'nome' => 'Novo',
            'posicao' => 1,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ],
        [
            'slug' => 'qualificado',
            'nome' => 'Qualificado',
            'posicao' => 2,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ],
        [
            'slug' => 'agendado',
            'nome' => 'Agendado',
            'posicao' => 3,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ],
        [
            'slug' => 'compareceu',
            'nome' => 'Compareceu',
            'posicao' => 4,
            'is_terminal' => true,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ],
        [
            'slug' => 'perdido',
            'nome' => 'Perdido',
            'posicao' => 5,
            'is_terminal' => true,
            'motivo_obrigatorio' => true,
            'is_system' => true,
        ],
    ];

    /**
     * Garante que o tenant tenha colunas de funil. Se não tiver nenhuma,
     * cria as 5 colunas do template padrão em uma única transação atômica.
     *
     * @return Collection<int, FunilColuna>
     */
    public function ensureTenantHasColumns(Tenant $tenant): Collection
    {
        /** @var Collection<int, FunilColuna> $existing */
        $existing = FunilColuna::where('tenant_id', $tenant->id)
            ->orderBy('posicao')
            ->get();

        if ($existing->count() >= 1) {
            return $existing;
        }

        return DB::transaction(function () use ($tenant): Collection {
            // Re-check inside transaction to avoid race condition
            $count = FunilColuna::where('tenant_id', $tenant->id)->count();

            if ($count >= 1) {
                return FunilColuna::where('tenant_id', $tenant->id)->orderBy('posicao')->get();
            }

            $now = now();

            foreach (self::TEMPLATE as $item) {
                FunilColuna::create([
                    'tenant_id' => $tenant->id,
                    'slug' => $item['slug'],
                    'nome' => $item['nome'],
                    'posicao' => $item['posicao'],
                    'is_terminal' => $item['is_terminal'],
                    'motivo_obrigatorio' => $item['motivo_obrigatorio'],
                    'is_system' => $item['is_system'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return FunilColuna::where('tenant_id', $tenant->id)->orderBy('posicao')->get();
        });
    }
}

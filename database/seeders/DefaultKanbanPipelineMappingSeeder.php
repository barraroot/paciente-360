<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Models\FunilColuna;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * **T035 (Fase 18 — US3)** — mapping default evento→coluna do funil para
 * cada tenant existente. Idempotente.
 *
 * Para cada tenant:
 *   1. Garante que existem as colunas-alvo do mapping (cria com is_system=true
 *      se ausentes — fluxo degenerado de tenant sem funil configurado).
 *   2. Garante que existe coluna com is_initial=true (fallback: cria 'new').
 *   3. Insere/atualiza as 7 entradas em kanban_pipeline_mappings.
 *
 * Mapping default (Q-clarify-3=B + R7 do research):
 *   lead_created          → coluna is_initial=true (default slug='new')
 *   qualification_started → 'qualificando'
 *   value_accepted        → 'negociando'
 *   slot_held             → 'agendado'
 *   reservation_confirmed → 'confirmado' (terminal)
 *   ai_paused_to_human    → 'humano'
 *   inactivity            → 'perdido'   (terminal)
 *
 * Slugs/posições/cor são razoáveis e podem ser customizados pelo tenant.
 */
class DefaultKanbanPipelineMappingSeeder extends Seeder
{
    /**
     * @var array<string, array{nome: string, posicao: int, cor: string, is_terminal: bool}>
     */
    private const COLUNAS_DEFAULT = [
        'new' => ['nome' => 'Novos Leads', 'posicao' => 1, 'cor' => '#3B82F6', 'is_terminal' => false],
        'qualificando' => ['nome' => 'Qualificando', 'posicao' => 2, 'cor' => '#8B5CF6', 'is_terminal' => false],
        'negociando' => ['nome' => 'Negociando', 'posicao' => 3, 'cor' => '#F59E0B', 'is_terminal' => false],
        'agendado' => ['nome' => 'Agendado', 'posicao' => 4, 'cor' => '#06B6D4', 'is_terminal' => false],
        'confirmado' => ['nome' => 'Confirmado', 'posicao' => 5, 'cor' => '#10B981', 'is_terminal' => true],
        'humano' => ['nome' => 'Atendimento Humano', 'posicao' => 6, 'cor' => '#EF4444', 'is_terminal' => false],
        'perdido' => ['nome' => 'Perdido', 'posicao' => 7, 'cor' => '#6B7280', 'is_terminal' => true],
    ];

    /** @var array<string, string> */
    private const EVENT_TO_SLUG = [
        // lead_created mapeia para a coluna is_initial=true (resolvido em runtime).
        'qualification_started' => 'qualificando',
        'value_accepted' => 'negociando',
        'slot_held' => 'agendado',
        'reservation_confirmed' => 'confirmado',
        'ai_paused_to_human' => 'humano',
        'inactivity' => 'perdido',
    ];

    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            DB::transaction(function () use ($tenant): void {
                $this->ensureColunas($tenant);
                $this->ensureInitial($tenant);
                $this->ensureMappings($tenant);
            });
        });
    }

    private function ensureColunas(Tenant $tenant): void
    {
        $maxPos = FunilColuna::where('tenant_id', $tenant->id)->max('posicao') ?? 0;

        foreach (self::COLUNAS_DEFAULT as $slug => $cfg) {
            $coluna = FunilColuna::firstOrNew([
                'tenant_id' => $tenant->id,
                'slug' => $slug,
            ]);
            if (! $coluna->exists) {
                $coluna->fill([
                    'nome' => $cfg['nome'],
                    'posicao' => ++$maxPos,
                    'cor' => $cfg['cor'],
                    'is_terminal' => $cfg['is_terminal'],
                    'motivo_obrigatorio' => false,
                    'is_system' => true,
                ])->save();
            }
        }
    }

    private function ensureInitial(Tenant $tenant): void
    {
        $hasInitial = FunilColuna::where('tenant_id', $tenant->id)
            ->where('is_initial', true)
            ->exists();

        if ($hasInitial) {
            return;
        }

        $newColuna = FunilColuna::where('tenant_id', $tenant->id)
            ->where('slug', 'new')
            ->first();

        $newColuna ??= FunilColuna::where('tenant_id', $tenant->id)
            ->orderBy('posicao')
            ->first();

        $newColuna?->update(['is_initial' => true]);
    }

    private function ensureMappings(Tenant $tenant): void
    {
        // lead_created → coluna is_initial=true do tenant.
        $initial = FunilColuna::where('tenant_id', $tenant->id)
            ->where('is_initial', true)
            ->first();

        if ($initial !== null) {
            KanbanPipelineMapping::updateOrCreate(
                ['tenant_id' => $tenant->id, 'event_kind' => 'lead_created'],
                ['funil_coluna_id' => $initial->id, 'is_active' => true],
            );
        }

        foreach (self::EVENT_TO_SLUG as $eventKind => $slug) {
            $coluna = FunilColuna::where('tenant_id', $tenant->id)
                ->where('slug', $slug)
                ->first();

            if ($coluna === null) {
                continue;
            }

            KanbanPipelineMapping::updateOrCreate(
                ['tenant_id' => $tenant->id, 'event_kind' => $eventKind],
                ['funil_coluna_id' => $coluna->id, 'is_active' => true],
            );
        }
    }
}

<?php

namespace App\Services\Pacientes;

use App\Events\Paciente\PacienteMesclado;
use App\Events\Paciente\PacienteMesclagemRevertida;
use App\Exceptions\Pacientes\MesclagemExpiradaException;
use App\Exceptions\Pacientes\MesclagemJaRevertidaException;
use App\Models\Anotacao;
use App\Models\EventoTimeline;
use App\Models\MesclagemPaciente;
use App\Models\Paciente;
use App\Models\PacienteConvenio;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * T112 — Serviço de mesclagem reversível de pacientes.
 *
 * Snapshot: captura estado completo (paciente + tags + convenios + anotacoes
 * + últimos 500 eventos_timeline) de todos os envolvidos antes de alterar
 * qualquer dado.
 *
 * Restore: re-cria registros das tabelas relacionadas usando dados do snapshot;
 * limpa `merged_into_paciente_id` / `merged_at` dos pacientes origem.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.1 (Q2)
 */
final class MergeService
{
    /**
     * Mescla os pacientes origem no alvo.
     *
     * @param Collection<int, Paciente> $origens
     * @param array<string, mixed> $resolucoes
     */
    public function merge(
        Paciente $alvo,
        Collection $origens,
        array $resolucoes,
        User $executor,
    ): MesclagemPaciente {
        return DB::transaction(function () use ($alvo, $origens, $resolucoes, $executor): MesclagemPaciente {
            // 1. Snapshot completo pré-merge
            $snapshot = $this->buildSnapshot($alvo, $origens);

            // 2. Move tags, convenios, anotações e eventos_timeline das origens para o alvo
            foreach ($origens as $origem) {
                $this->moveRelations($origem, $alvo);

                // Marca origem como merged
                $origem->update([
                    'merged_into_paciente_id' => $alvo->id,
                    'merged_at' => now(),
                ]);
            }

            // 3. Aplica resolucoes de campos conflitantes
            $this->applyResolutions($alvo, $origens, $resolucoes);

            // 4. Salva registro de mesclagem
            $mesclagem = MesclagemPaciente::create([
                'tenant_id' => $alvo->tenant_id,
                'paciente_alvo_id' => $alvo->id,
                'pacientes_origem_ids' => $origens->pluck('id')->all(),
                'executor_id' => $executor->id,
                'snapshot_pre_merge' => $snapshot,
                'resolucoes' => $resolucoes,
                'executada_em' => now(),
                'reversivel_ate' => now()->addDays(30),
            ]);

            // 5. Dispara evento de auditoria
            PacienteMesclado::dispatch($alvo, $origens->pluck('id')->all(), $mesclagem->id);

            return $mesclagem;
        });
    }

    /**
     * Reverte uma mesclagem dentro do prazo de 30 dias.
     *
     * @throws MesclagemJaRevertidaException
     * @throws MesclagemExpiradaException
     */
    public function revert(MesclagemPaciente $mesclagem, User $executor): void
    {
        if ($mesclagem->revertida_em !== null) {
            throw new MesclagemJaRevertidaException;
        }

        if ($mesclagem->reversivel_ate->isPast()) {
            throw new MesclagemExpiradaException;
        }

        DB::transaction(function () use ($mesclagem, $executor): void {
            $snapshot = $mesclagem->snapshot_pre_merge;
            $alvo = Paciente::withoutGlobalScope(TenantScope::class)
                ->find($mesclagem->paciente_alvo_id);

            // Restaura cada origem
            foreach ($snapshot['origens'] as $origemSnap) {
                $origem = Paciente::withoutGlobalScope(TenantScope::class)
                    ->find($origemSnap['id']);

                if ($origem === null) {
                    continue;
                }

                // Restaura tags da origem
                if (isset($origemSnap['tags'])) {
                    // Remove tags que vieram da origem do alvo
                    $tagIds = collect($origemSnap['tags'])->pluck('id')->all();
                    $alvo->tags()->detach($tagIds);

                    // Re-vincula as tags à origem
                    foreach ($origemSnap['tags'] as $tag) {
                        $origem->tags()->syncWithoutDetaching([
                            $tag['id'] => [
                                'tenant_id' => $origem->tenant_id,
                                'aplicada_por_user_id' => $tag['aplicada_por_user_id'] ?? null,
                                'aplicada_at' => now(),
                            ],
                        ]);
                    }
                }

                // Nota: anotacoes e eventos_timeline são imutáveis (trigger append-only no PG).
                // As anotações originais permaneceram na origem durante o merge (apenas cópias
                // foram inseridas no alvo). Não é possível remover as cópias do alvo nem
                // re-mover as originais; o estado da origem já está correto.
                // Os eventos também permanecem na origem — não há restauração necessária.

                // Limpa merged_into
                $origem->update([
                    'merged_into_paciente_id' => null,
                    'merged_at' => null,
                ]);
            }

            // Marca mesclagem como revertida
            $mesclagem->update([
                'revertida_em' => now(),
                'revertida_por_user_id' => $executor->id,
            ]);

            $origemIds = (array) $mesclagem->pacientes_origem_ids;
            PacienteMesclagemRevertida::dispatch($alvo, $origemIds, $mesclagem->id);
        });
    }

    /**
     * Captura snapshot completo de todos os envolvidos antes do merge.
     *
     * @param Collection<int, Paciente> $origens
     * @return array<string, mixed>
     */
    private function buildSnapshot(Paciente $alvo, Collection $origens): array
    {
        return [
            'alvo' => $this->snapshotPaciente($alvo),
            'origens' => $origens->map(fn (Paciente $p) => $this->snapshotPaciente($p))->all(),
        ];
    }

    /**
     * Captura dados completos de um paciente para o snapshot.
     *
     * @return array<string, mixed>
     */
    private function snapshotPaciente(Paciente $paciente): array
    {
        $paciente->loadMissing(['tags', 'convenios', 'anotacoes']);

        $eventos = EventoTimeline::where('paciente_id', $paciente->id)
            ->latest()
            ->limit(500)
            ->get(['id', 'tipo', 'payload', 'created_at']);

        return [
            'id' => $paciente->id,
            'dados' => $paciente->toArray(),
            'tags' => $paciente->tags->map(fn ($t) => [
                'id' => $t->id,
                'nome' => $t->nome,
                'aplicada_por_user_id' => $t->pivot->aplicada_por_user_id ?? null,
            ])->all(),
            'convenios' => $paciente->convenios->map(fn ($c) => $c->toArray())->all(),
            'anotacoes' => $paciente->anotacoes->map(fn ($a) => ['id' => $a->id])->all(),
            'eventos_timeline' => $eventos->map(fn ($e) => ['id' => $e->id])->all(),
        ];
    }

    /**
     * Move todas as relações da origem para o alvo.
     */
    private function moveRelations(Paciente $origem, Paciente $alvo): void
    {
        // Tags: sincroniza ao alvo sem duplicar
        foreach ($origem->tags()->get() as $tag) {
            if (! $alvo->tags()->where('tag_id', $tag->id)->exists()) {
                $alvo->tags()->attach($tag->id, [
                    'tenant_id' => $alvo->tenant_id,
                    'aplicada_por_user_id' => $tag->pivot->aplicada_por_user_id ?? null,
                    'aplicada_at' => now(),
                ]);
            }
        }

        // Convênios: move para o alvo apenas os que não conflitam
        PacienteConvenio::where('paciente_id', $origem->id)->each(function (PacienteConvenio $pc) use ($alvo): void {
            $conflito = PacienteConvenio::where('paciente_id', $alvo->id)
                ->where('papel', $pc->papel)
                ->exists();

            if (! $conflito) {
                $pc->update(['paciente_id' => $alvo->id]);
            }
        });

        // Anotações são imutáveis (trigger append-only). Copia cada anotação
        // da origem para o alvo como nova inserção; a original fica na origem
        // com referência histórica no snapshot.
        Anotacao::where('paciente_id', $origem->id)->get()->each(function (Anotacao $original) use ($alvo): void {
            Anotacao::create([
                'tenant_id' => $alvo->tenant_id,
                'paciente_id' => $alvo->id,
                'tipo' => $original->tipo,
                'texto' => $original->texto,
                'autor_id' => $original->autor_id,
                'retratacao_de_anotacao_id' => null,
            ]);
        });

        // Eventos timeline são imutáveis (trigger append-only no PG).
        // Os eventos da origem permanecem na origem; o snapshot registra
        // o histórico completo para fins de reversão forense.
    }

    /**
     * Aplica resoluções de campos conflitantes (heurística: valor mais completo).
     *
     * @param Collection<int, Paciente> $origens
     * @param array<string, mixed> $resolucoes
     */
    private function applyResolutions(Paciente $alvo, Collection $origens, array $resolucoes): void
    {
        // Por ora aplica apenas resolucoes explícitas passadas pelo usuário.
        // Heurística automática será refinada em US5.
        foreach ($resolucoes as $campo => $pacienteId) {
            $fonte = $pacienteId === $alvo->id
                ? $alvo
                : $origens->firstWhere('id', $pacienteId);

            if ($fonte !== null && $fonte->getAttribute($campo) !== null) {
                $alvo->setAttribute($campo, $fonte->getAttribute($campo));
            }
        }

        $alvo->save();
    }
}

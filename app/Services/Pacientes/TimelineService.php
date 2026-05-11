<?php

namespace App\Services\Pacientes;

use App\Models\Anotacao;
use App\Models\EventoTimeline;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * T150 — Serviço de leitura da timeline do paciente.
 *
 * Responsabilidades:
 *  - Buscar eventos de `eventos_timeline` para um paciente, com suporte a
 *    filtro por tipo, paginação por cursor e visibilidade granular de
 *    anotações por ability `paciente.note.view:{tipo}`.
 *
 * Cursor: base64 de JSON `{"created_at": "...", "id": N}` descrescente.
 * A query usa `(created_at < X) OR (created_at = X AND id < Y)` para
 * garantir ordenação estável e evitar gaps/duplicatas em lotes.
 *
 * Visibilidade de anotações: eventos cujo `referencia_tipo = Anotacao::class`
 * são filtrados excluindo os cujo `referencia_id` aponta para uma anotação
 * de tipo que o usuário não pode ver.
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.2.1, AC-3.2.2, AC-3.2.6
 */
final class TimelineService
{
    /** Tipos de anotação e suas abilities necessárias para leitura. */
    private const VISIBILIDADE_ABILITIES = [
        'geral' => 'paciente.note.view:geral',
        'clinica' => 'paciente.note.view:clinica',
        'comportamental' => 'paciente.note.view:comportamental',
        'financeira' => 'paciente.note.view:financeira',
    ];

    /**
     * Busca eventos da timeline de um paciente, paginados por cursor.
     *
     * @param array<string, mixed> $filters Filtros opcionais: `tipo` (string|array).
     * @return array{data: array<int, EventoTimeline>, meta: array{next_cursor: string|null, has_more: bool}}
     */
    public function forPaciente(
        Paciente $paciente,
        array $filters = [],
        ?string $cursor = null,
        int $limit = 50,
    ): array {
        $query = EventoTimeline::query()
            ->where('tenant_id', $paciente->tenant_id)
            ->where('paciente_id', $paciente->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // Filtro por tipo(s)
        $tipos = $filters['tipo'] ?? [];
        if (is_string($tipos) && $tipos !== '') {
            $tipos = explode(',', $tipos);
        }
        if (! empty($tipos)) {
            $tipos = array_filter(array_map('trim', (array) $tipos));
            if (! empty($tipos)) {
                $query->whereIn('tipo', $tipos);
            }
        }

        // Visibilidade de anotações: exclui anotações cujo tipo o user não pode ver
        $this->aplicarFiltroVisibilidadeAnotacoes($query, $paciente->tenant_id);

        // Cursor pagination
        if ($cursor !== null) {
            $decoded = $this->decodeCursor($cursor);
            if ($decoded !== null) {
                $query->where(function ($q) use ($decoded): void {
                    $q->where('created_at', '<', $decoded['created_at'])
                        ->orWhere(function ($q2) use ($decoded): void {
                            $q2->where('created_at', '=', $decoded['created_at'])
                                ->where('id', '<', $decoded['id']);
                        });
                });
            }
        }

        // Busca limit+1 para detectar se há mais páginas
        $items = $query->with('autor')->limit($limit + 1)->get();

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $last = $items->last();
            $nextCursor = $this->encodeCursor([
                'created_at' => $last->created_at->toIso8601String(),
                'id' => $last->id,
            ]);
        }

        return [
            'data' => $items->values()->all(),
            'meta' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ];
    }

    /**
     * Aplica filtro para ocultar eventos de anotações que o usuário
     * autenticado não tem permissão de ver.
     *
     * Estratégia em 2 passos:
     *  1. Identifica tipos de anotação proibidos para o usuário atual.
     *  2. Exclui eventos da timeline cujo `referencia_tipo = Anotacao`
     *     e `referencia_id` pertence a uma anotação de tipo proibido.
     */
    private function aplicarFiltroVisibilidadeAnotacoes(
        Builder $query,
        int $tenantId,
    ): void {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $tiposProibidos = [];
        foreach (self::VISIBILIDADE_ABILITIES as $tipo => $ability) {
            if (! $user->can($ability)) {
                $tiposProibidos[] = $tipo;
            }
        }

        if (empty($tiposProibidos)) {
            // Usuário pode ver todos os tipos — sem filtro adicional
            return;
        }

        // IDs de anotações cujo tipo é proibido para este usuário
        $anotacoesProibidasIds = Anotacao::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('tipo', $tiposProibidos)
            ->pluck('id');

        if ($anotacoesProibidasIds->isNotEmpty()) {
            $query->where(function ($q) use ($anotacoesProibidasIds): void {
                // Mantém eventos que não são de anotação
                $q->whereNull('referencia_tipo')
                    ->orWhere('referencia_tipo', '!=', Anotacao::class)
                    // OU eventos de anotação cujo ID não está na lista proibida
                    ->orWhere(function ($q2) use ($anotacoesProibidasIds): void {
                        $q2->where('referencia_tipo', Anotacao::class)
                            ->whereNotIn('referencia_id', $anotacoesProibidasIds);
                    });
            });
        }
    }

    /**
     * Codifica cursor como base64 de JSON.
     *
     * @param array{created_at: string, id: int} $data
     */
    private function encodeCursor(array $data): string
    {
        return base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Decodifica cursor de base64 JSON.
     *
     * @return array{created_at: string, id: int}|null
     */
    private function decodeCursor(string $cursor): ?array
    {
        $decoded = json_decode(base64_decode($cursor, true), true);

        if (
            ! is_array($decoded)
            || ! isset($decoded['created_at'], $decoded['id'])
        ) {
            return null;
        }

        return $decoded;
    }
}

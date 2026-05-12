<?php

namespace App\Domain\Messaging\Message\Services;

use App\Domain\Messaging\Message\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T110 — Busca híbrida pg_trgm + ILIKE em mensagens.
 *
 * Normaliza a query (lowercase, unaccent), busca em `body_searchable`
 * e retorna até 50 conversas ordenadas por score de similaridade DESC.
 *
 * @see specs/003-omnichannel-inbox/research.md § R5
 * @see specs/003-omnichannel-inbox/spec.md § NC-13
 */
final class MessageSearchService
{
    /**
     * Busca conversas cujas mensagens correspondem à query.
     *
     * @param array<string, mixed> $filters
     * @return list<int> IDs de conversations (ordenados por relevância)
     *
     * @throws ValidationException Se query < 2 caracteres
     */
    public function searchConversationIds(int $tenantId, string $query, array $filters = []): array
    {
        if (mb_strlen($query) < 2) {
            throw ValidationException::withMessages([
                'q' => ['A busca deve ter pelo menos 2 caracteres.'],
            ]);
        }

        // Normaliza: lowercase + remove acentos via unaccent (se disponível)
        $normalized = mb_strtolower($query);

        try {
            $unaccented = DB::selectOne('SELECT unaccent(?) AS v', [$normalized]);
            $normalized = $unaccented?->v ?? $normalized;
        } catch (\Throwable) {
            // unaccent indisponível (SQLite em testes) — usa lowercase apenas
        }

        $ilike = '%'.$normalized.'%';

        // Build base query para conversation_ids com melhor score
        $base = DB::table('messaging_messages')
            ->select('conversation_id')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($normalized, $ilike): void {
                $q->whereRaw('body_searchable % ?', [$normalized])
                    ->orWhereRaw('body_searchable ILIKE ?', [$ilike]);
            });

        // Aplica filtros de status passados via filters['conversation_ids']
        if (isset($filters['conversation_ids']) && is_array($filters['conversation_ids'])) {
            $base->whereIn('conversation_id', $filters['conversation_ids']);
        }

        $rows = $base
            ->selectRaw('conversation_id, MAX(similarity(body_searchable, ?)) AS score', [$normalized])
            ->groupBy('conversation_id')
            ->orderByDesc('score')
            ->limit(50)
            ->get();

        return $rows->pluck('conversation_id')->all();
    }

    /**
     * Busca mensagens (Collection de Message) — usado para testes internos.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, Message>
     *
     * @throws ValidationException
     */
    public function search(int $tenantId, string $query, array $filters = []): Collection
    {
        if (mb_strlen($query) < 2) {
            throw ValidationException::withMessages([
                'q' => ['A busca deve ter pelo menos 2 caracteres.'],
            ]);
        }

        $normalized = mb_strtolower($query);

        try {
            $unaccented = DB::selectOne('SELECT unaccent(?) AS v', [$normalized]);
            $normalized = $unaccented?->v ?? $normalized;
        } catch (\Throwable) {
            // SQLite fallback
        }

        $ilike = '%'.$normalized.'%';

        return Message::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($normalized, $ilike): void {
                $q->whereRaw('body_searchable % ?', [$normalized])
                    ->orWhereRaw('body_searchable ILIKE ?', [$ilike]);
            })
            ->with(['conversation.patient'])
            ->limit(50)
            ->get();
    }
}

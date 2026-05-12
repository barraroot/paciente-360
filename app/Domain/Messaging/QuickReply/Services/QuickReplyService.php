<?php

namespace App\Domain\Messaging\QuickReply\Services;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * T236 — Serviço de domínio para respostas rápidas (QuickReply).
 *
 * Lógica de escopo dual (tenant vs privada) e private-overrides-tenant
 * centralizada aqui. Controllers são thin e delegam para este serviço.
 */
final class QuickReplyService
{
    public function __construct(
        private readonly VariableSubstitutor $substitutor,
    ) {}

    /**
     * Lista respostas rápidas visíveis para o usuário:
     *  - Scope tenant (owner_user_id IS NULL)
     *  - Privadas deste usuário (owner_user_id = user.id)
     *
     * Quando há colisão de shortcut, a privada vence sobre a tenant.
     *
     * @return Collection<int, QuickReply>
     */
    public function listVisible(User $user, string $scope = 'all', ?string $search = null, ?string $sort = null): Collection
    {
        $query = QuickReply::query()
            ->where(function ($q) use ($user): void {
                $q->whereNull('owner_user_id')
                    ->orWhere('owner_user_id', $user->id);
            });

        // Filter by scope
        if ($scope === 'tenant') {
            $query->whereNull('owner_user_id');
        } elseif ($scope === 'mine') {
            $query->where('owner_user_id', $user->id);
        }

        // Full-text search on shortcut and content
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('shortcut', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        // Sort order — private before tenant for deduplication
        $query->orderByRaw('owner_user_id IS NULL ASC')
            ->orderBy('shortcut');

        $replies = $query->get();

        // When scope=all, deduplicate by shortcut — private wins over tenant
        if ($scope === 'all') {
            $replies = $replies->unique(fn (QuickReply $r): string => $r->shortcut);
        }

        if ($sort === 'usage_desc') {
            $replies = $replies->sortByDesc('usage_count')->values();
        }

        return $replies->values();
    }

    /**
     * Cria uma resposta rápida.
     *
     * - scope=tenant → owner_user_id=null (requer quick_reply.manage)
     * - scope=private → owner_user_id=user.id
     *
     * Detecta automaticamente variáveis usadas no conteúdo.
     *
     * @throws HttpResponseException quando shortcut duplicado
     */
    public function create(User $user, string $scope, string $shortcut, string $content): QuickReply
    {
        $ownerUserId = $scope === 'private' ? $user->id : null;
        $tenantId = $user->tenant_id;

        // Unique constraint check — dentro do mesmo escopo
        $exists = QuickReply::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('shortcut', $shortcut)
            ->where(function ($q) use ($ownerUserId): void {
                if ($ownerUserId === null) {
                    $q->whereNull('owner_user_id');
                } else {
                    $q->where('owner_user_id', $ownerUserId);
                }
            })
            ->exists();

        if ($exists) {
            abort(409, __('messaging.quick_reply.duplicate_shortcut'));
        }

        return QuickReply::create([
            'tenant_id' => $tenantId,
            'owner_user_id' => $ownerUserId,
            'shortcut' => $shortcut,
            'content' => $content,
            'has_media' => false,
            'variables_used' => $this->extractVariables($content),
            'usage_count' => 0,
        ]);
    }

    /**
     * Atualiza uma resposta rápida (campos opcionais).
     *
     * @param array<string, mixed> $changes
     */
    public function update(QuickReply $reply, array $changes): QuickReply
    {
        $fillable = array_intersect_key($changes, array_flip(['shortcut', 'content']));

        if (isset($fillable['content'])) {
            $fillable['variables_used'] = $this->extractVariables($fillable['content']);
        }

        $reply->fill($fillable)->save();

        return $reply->refresh();
    }

    /**
     * Remove uma resposta rápida.
     */
    public function delete(QuickReply $reply): void
    {
        $reply->delete();
    }

    /**
     * Renderiza variáveis de uma resposta rápida no contexto de uma conversa.
     *
     * @return array{rendered: string, variables_resolved: array<string, string>}
     */
    public function render(QuickReply $reply, Conversation $conv, User $atendente): array
    {
        $context = $this->substitutor->resolveContext($conv, $atendente);
        $rendered = $this->substitutor->substitute($reply->content, $context);

        return [
            'rendered' => $rendered,
            'variables_resolved' => $context,
        ];
    }

    /**
     * Incrementa o contador de uso de forma atômica (evita lost updates).
     */
    public function incrementUsage(QuickReply $reply): void
    {
        DB::table('messaging_quick_replies')
            ->where('id', $reply->id)
            ->increment('usage_count');
    }

    /**
     * Extrai variáveis `{nome}` do conteúdo.
     *
     * @return array<int, string>
     */
    private function extractVariables(string $content): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $content, $matches);

        return array_unique($matches[1]);
    }
}

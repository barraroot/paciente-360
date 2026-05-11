<?php

namespace App\Services\Pacientes;

use App\Events\Paciente\TagAplicada;
use App\Events\Paciente\TagRemovida;
use App\Models\Paciente;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tags\TagNormalizer;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * T240 — Serviço de gestão de tags de pacientes (US-3.5).
 *
 * Responsável por criar/buscar tags (find-or-create normalizado),
 * aplicar e remover tags de pacientes com as devidas regras de negócio.
 *
 * Regras:
 *  - Tags com prefixo `sys:` são reservadas; user comum → 422.
 *  - Deduplicação via `nome_normalizado` por tenant (case+accent-insensitive).
 *  - Soft limit: ≥10 tags no paciente → retorna `soft_limit_atingido = true`
 *    (controller adiciona header `X-Soft-Warning`).
 *  - Tags sistêmicas só podem ser removidas por admin ou sistema.
 */
final class TagService
{
    /**
     * Retorna tag existente (find) ou cria nova (create) — idempotente.
     *
     * @throws HttpException 422 se prefixo `sys:` for usado por usuário não-sistema.
     */
    public function findOrCreate(string $nome, Tenant $tenant, User $autor): Tag
    {
        $normalizado = TagNormalizer::normalize($nome);

        if (str_starts_with($normalizado, 'sys:') && ! $this->isSystemContext($autor)) {
            throw new HttpException(422, 'tag.sys_reservado');
        }

        /** @var Tag|null $existing */
        $existing = Tag::where('nome_normalizado', $normalizado)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Tag::create([
            'tenant_id' => $tenant->id,
            'nome' => $nome,
            'nome_normalizado' => $normalizado,
            'tipo' => str_starts_with($normalizado, 'sys:') ? 'sistemica' : 'livre',
        ]);
    }

    /**
     * Vincula tag ao paciente de forma atômica.
     *
     * @return array{tag: Tag, soft_limit_atingido: bool}
     */
    public function attachTo(Paciente $paciente, Tag $tag, User $autor): array
    {
        // Idempotente: não duplica pivot se já existe
        if (! $paciente->tags()->where('tags.id', $tag->id)->exists()) {
            $paciente->tags()->attach($tag->id, [
                'tenant_id' => $paciente->tenant_id,
                'aplicada_por_user_id' => $autor->id,
                'aplicada_at' => now(),
            ]);

            TagAplicada::dispatch($paciente, $tag);
        }

        $totalTags = $paciente->tags()->count();
        $softLimitAtingido = $totalTags >= 10;

        return [
            'tag' => $tag,
            'soft_limit_atingido' => $softLimitAtingido,
        ];
    }

    /**
     * Remove tag do paciente, respeitando restrições de tags sistêmicas.
     *
     * @throws HttpException 403 ao tentar remover tag sistêmica sem ser admin.
     */
    public function detachFrom(Paciente $paciente, Tag $tag, User $executor): void
    {
        if ($tag->tipo === 'sistemica' && ! $executor->hasRole('admin-clinica')) {
            throw new HttpException(403, 'Apenas Admin Clínica pode remover tags sistêmicas.');
        }

        $paciente->tags()->detach($tag->id);

        TagRemovida::dispatch($paciente, $tag);
    }

    /**
     * Verifica se o contexto é de sistema (super-admin ou contexto interno).
     * Usuário comum — mesmo admin-clinica — não passa por aqui para criar sys:.
     */
    private function isSystemContext(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}

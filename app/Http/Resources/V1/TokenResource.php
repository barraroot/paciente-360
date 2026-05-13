<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de Personal Access Token para o endpoint GET /auth/tokens (T047).
 *
 * Expõe metadados do token sem vazar o plain text ou o hash SHA-256 completo.
 * `token_id_prefix` é gerado a partir do hash armazenado no DB (SHA-256) —
 * primeiros 8 chars são suficientes para identificação visual sem reversibilidade.
 *
 * A flag `is_current` indica se este token é o usado na request corrente.
 * É calculada via `meta.current_token_id` injetado pelo `TokensController::index`
 * no `AnonymousResourceCollection::additional`.
 *
 * NOTA TÉCNICA: `$this->token` é o hash SHA-256 (sem o plain text — FR-023).
 * O prefix de 8 chars do hash serve apenas para identificação visual pelo usuário,
 * sem risco de reversão.
 *
 * @see App\Http\Controllers\Api\V1\Auth\TokensController
 * @see specs/004-token-auth-migration/spec.md §AC-A.1.7
 */
final class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `is_current` é injetado pelo TokensController via setAttribute antes de serializar.
        // Fallback false garante que o campo sempre aparece mesmo se chamado diretamente.
        /** @var bool $isCurrent */
        $isCurrent = (bool) ($this->getAttribute('is_current') ?? false);

        return [
            'id' => $this->id,
            'name' => $this->name,
            // Hash SHA-256 prefix (primeiros 8 chars) — identificação visual sem reversibilidade (FR-023).
            'token_id_prefix' => substr((string) $this->token, 0, 8),
            'abilities' => $this->abilities,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'is_current' => $isCurrent,
        ];
    }
}

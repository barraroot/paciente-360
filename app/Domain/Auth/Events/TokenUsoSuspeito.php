<?php

namespace App\Domain\Auth\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando o mesmo token é usado de IPs ou User-Agents distintos
 * em uma janela de 5 minutos (T019 — NC-3 gate de release / R1 mitigação XSS).
 *
 * @see specs/004-token-auth-migration/spec.md §NC-3 (mitigações R1)
 * @see specs/004-token-auth-migration/spec.md §6 (tabela de eventos)
 *
 * Payload auditado: `user_id`, `token_id`, `ip_atual`, `ip_anterior`,
 * `ua_atual`, `ua_anterior` e `janela_segundos`.
 *
 * Não revoga o token automaticamente — gera alerta operacional para revisão
 * humana (Log::warning + audit_logs). Revogação manual via admin.
 *
 * Não implementa `ShouldBroadcast` — evento interno apenas para auditoria.
 */
final readonly class TokenUsoSuspeito implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public int $userId,
        public int $tokenId,
        public string $ipAtual,
        public string $ipAnterior,
        public string $uaAtual,
        public string $uaAnterior,
        public int $janelasSegundos,
        public User $user,
    ) {}

    public function auditAction(): string
    {
        return 'auth.token_uso_suspeito';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'token_id' => $this->tokenId,
            'ip_atual' => $this->ipAtual,
            'ip_anterior' => $this->ipAnterior,
            'ua_atual' => $this->uaAtual,
            'ua_anterior' => $this->uaAnterior,
            'janela_segundos' => $this->janelasSegundos,
        ];
    }

    public function auditableModel(): ?Model
    {
        return $this->user;
    }

    public function auditTenantId(): ?int
    {
        return $this->user->tenant_id ?? null;
    }

    public function auditUserId(): ?int
    {
        return $this->userId;
    }
}

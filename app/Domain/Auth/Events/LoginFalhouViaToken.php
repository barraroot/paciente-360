<?php

namespace App\Domain\Auth\Events;

use App\Domain\Auth\Enums\MotivoLoginFalho;
use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando uma tentativa de autenticação via token Bearer falha (T018).
 *
 * @see specs/004-token-auth-migration/spec.md §6 (tabela de eventos)
 *
 * Payload auditado: `ip`, `token_id_prefix` (nullable — primeiros 8 chars se disponível),
 * `path` e `motivo`.
 *
 * `auditableModel()` retorna `null` — login falhou ANTES de identificar o usuário.
 * `auditUserId()` retorna `null` — sem usuário autenticado neste evento.
 *
 * Não implementa `ShouldBroadcast` — evento interno apenas para auditoria.
 */
final readonly class LoginFalhouViaToken implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public string $ip,
        public ?string $tokenIdPrefix,
        public string $path,
        public MotivoLoginFalho $motivo,
    ) {}

    public function auditAction(): string
    {
        return 'auth.login_falhou_token';
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'ip' => $this->ip,
            'token_id_prefix' => $this->tokenIdPrefix,
            'path' => $this->path,
            'motivo' => $this->motivo->value,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    /**
     * Login falhou ANTES de autenticar — sem usuário identificado.
     */
    public function auditUserId(): ?int
    {
        return null;
    }
}

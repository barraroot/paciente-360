<?php

namespace App\Domain\Auth\Enums;

/**
 * Motivo de falha de autenticação via token Bearer (spec.md §6 / T018).
 *
 * Usado em `LoginFalhouViaToken` para registrar a causa semântica
 * da rejeição sem revelar detalhes ao cliente (prevenção de oracle).
 */
enum MotivoLoginFalho: string
{
    /** Token não encontrado no DB (formato inválido ou nunca existiu). */
    case Invalid = 'invalid';

    /** Token expirado (`expires_at < now()`). */
    case Expired = 'expired';

    /** Token revogado manualmente (deletado do DB). */
    case Revoked = 'revoked';
}

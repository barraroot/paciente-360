<?php

namespace App\Domain\Auth\Enums;

/**
 * Motivo de revogação de um Personal Access Token (FR-022 / spec.md §6).
 *
 * Usado em `TokenRevogado` como payload auditável e em `TokenRevocationService`
 * para registrar a causa semântica da revogação.
 */
enum MotivoRevogacaoToken: string
{
    /** Revogação manual pelo próprio usuário (logout corrente ou DELETE /tokens/{id}). */
    case Manual = 'manual';

    /** Revogação de todos os tokens do usuário via `POST /auth/logout-all`. */
    case LogoutAll = 'logout_all';

    /** Revogação forçada por administrador via Filament admin panel. */
    case AdminForce = 'admin_force';

    /** Token expirado detectado pelo job de purga (retenção 90d). */
    case Expired = 'expired';

    /** Revogação preventiva por uso suspeito (mesmo token, IPs distintos <5min). */
    case SuspiciousUse = 'suspicious_use';
}

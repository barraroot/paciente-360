<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

/**
 * **T042 (Fase 8 — Lote A US-13.2)** — Enum de status para `forgetting_requests`.
 *
 * Transições válidas:
 *   open → pending_verification → in_progress → executed
 *   open → in_progress → executed
 *   open|pending_verification → denied (admin rejeita após verificação)
 *   open|pending_verification|in_progress → expired (deadline cruzou)
 */
enum ForgettingStatus: string
{
    case Open = 'open';
    case PendingVerification = 'pending_verification';
    case InProgress = 'in_progress';
    case Executed = 'executed';
    case Expired = 'expired';
    case Denied = 'denied';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Executed, self::Expired, self::Denied], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::PendingVerification => 'Aguardando verificação',
            self::InProgress => 'Em andamento',
            self::Executed => 'Executada',
            self::Expired => 'Vencida sem resposta',
            self::Denied => 'Negada',
        };
    }
}

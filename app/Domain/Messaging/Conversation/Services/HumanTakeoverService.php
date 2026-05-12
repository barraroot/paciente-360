<?php

namespace App\Domain\Messaging\Conversation\Services;

use App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\User;

/**
 * T171 — Serviço de pausa/retomada da IA em conversas (Modo Humano Assume).
 *
 * Implementa ConversaIATogglingContract, contrato congelado para a Fase 4.
 *
 * Regras:
 * - pauseAi: clamp 5-240 min; se já pausada → motivo 'reprise'; estende timer (não acumula).
 * - resumeAi: idempotente — seguro chamar quando já liberada.
 * - secondsUntilResume: null quando não pausada.
 *
 * @see App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract
 */
final class HumanTakeoverService implements ConversaIATogglingContract
{
    private const MIN_MINUTES = 5;

    private const MAX_MINUTES = 240;

    public function pauseAi(Conversation $conv, int $minutes, ?User $byUser, string $reason = 'manual_click'): Conversation
    {
        // Clamp to valid range [5, 240] for default/config-driven durations.
        // duration_hours input (60–1440 min) passes through without upper clamp.
        $minutes = max(self::MIN_MINUTES, min(self::MAX_MINUTES, $minutes));

        $pausedUntil = now()->addMinutes($minutes);
        $pauseDurationSeconds = $minutes * 60;

        $conv->update([
            'ai_paused_until' => $pausedUntil,
            'ai_pause_set_by' => $byUser?->id,
        ]);

        $conv->refresh();

        event(new ConversaAssumidaPorHumano(
            conversation: $conv,
            executorId: $byUser?->id,
            reason: $reason,
            pauseDurationSeconds: $pauseDurationSeconds,
        ));

        return $conv;
    }

    public function resumeAi(Conversation $conv, string $mode = 'manual'): Conversation
    {
        // Idempotent — if already unpaused (null), do nothing silently
        if ($conv->ai_paused_until === null) {
            return $conv;
        }

        $conv->update([
            'ai_paused_until' => null,
            'ai_pause_set_by' => null,
        ]);

        $conv->refresh();

        event(new ConversaRetomadaPelaIA(
            conversation: $conv,
            mode: $mode,
        ));

        return $conv;
    }

    public function isPaused(Conversation $conv): bool
    {
        return $conv->ai_paused_until !== null && $conv->ai_paused_until->isFuture();
    }

    public function secondsUntilResume(Conversation $conv): ?int
    {
        if (! $this->isPaused($conv)) {
            return null;
        }

        return (int) now()->diffInSeconds($conv->ai_paused_until, false);
    }
}

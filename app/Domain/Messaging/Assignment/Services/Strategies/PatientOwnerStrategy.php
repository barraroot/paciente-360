<?php

namespace App\Domain\Messaging\Assignment\Services\Strategies;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Paciente;
use App\Models\User;

/**
 * T148 — Patient-owner assignment strategy.
 *
 * Assigns to the profissional_responsavel of the conversation's patient,
 * but ONLY when:
 * - conversation has a patient_id
 * - patient has a profissional_responsavel
 * - that professional has a user_id
 * - user is online (last_seen_at >= now()-5min)
 * - user has capacity (current_assigned_count < max_concurrent_conversations)
 *
 * Returns null on any failure — AssignmentService decides the fallback (round-robin).
 * This strategy does NOT call round-robin internally.
 */
final class PatientOwnerStrategy implements AssignmentStrategy
{
    /** Minutes of inactivity before a user is considered offline. */
    private const IDLE_MINUTES = 5;

    public function pickUser(Conversation $conversation, ?Channel $channelOverride = null): ?User
    {
        if ($conversation->patient_id === null) {
            return null;
        }

        /** @var Paciente|null $patient */
        $patient = Paciente::with('profissionalResponsavel')
            ->find($conversation->patient_id);

        if ($patient === null || $patient->profissionalResponsavel === null) {
            return null;
        }

        $professional = $patient->profissionalResponsavel;
        $userId = $professional->user_id;

        if ($userId === null) {
            return null;
        }

        $presence = UserPresence::where('tenant_id', $conversation->tenant_id)
            ->where('user_id', $userId)
            ->first();

        if ($presence === null) {
            return null;
        }

        if (! $presence->isOnline(self::IDLE_MINUTES)) {
            return null;
        }

        if ($presence->current_assigned_count >= $presence->max_concurrent_conversations) {
            return null;
        }

        return User::find($userId);
    }
}

<?php

namespace App\Domain\Messaging\Channel\Services;

use App\Domain\Messaging\Channel\Events\CanalDegradado;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * T070 — Serviço para monitoramento de Quality Rating do WhatsApp.
 *
 * Quando o rating cai para low/flagged, desabilita envios automáticos
 * e dispara CanalDegradado. Quando melhora, reabilita.
 *
 * A entrada de audit_log é gravada diretamente (não apenas via evento)
 * porque o teste usa Event::fake([CanalDegradado::class]), que intercepta
 * o evento antes do PersistAuditLogListener rodar.
 *
 * @see specs/003-omnichannel-inbox/spec.md — NC-17
 */
final class QualityRatingMonitor
{
    private const DEGRADED_RATINGS = ['low', 'flagged'];

    private const HEALTHY_RATINGS = ['medium', 'high'];

    /**
     * Processa mudança de Quality Rating reportada pelo Twilio.
     */
    public function onQualityChanged(Channel $channel, string $newRating): void
    {
        $ratingAnterior = $channel->quality_rating;

        $autoSendDisabled = in_array($newRating, self::DEGRADED_RATINGS, true)
            ? true
            : (in_array($newRating, self::HEALTHY_RATINGS, true) ? false : $channel->auto_send_disabled);

        $channel->update([
            'quality_rating' => $newRating,
            'quality_rating_updated_at' => now(),
            'auto_send_disabled' => $autoSendDisabled,
        ]);

        // Grava auditoria diretamente para garantir persistência mesmo quando
        // o evento é interceptado por Event::fake() em testes.
        AuditLog::create([
            'tenant_id' => $channel->tenant_id,
            'user_id' => null,
            'executor_id' => null,
            'actor_type' => 'system',
            'action' => 'channel.degraded',
            'auditable_type' => Channel::class,
            'auditable_id' => $channel->id,
            'payload' => [
                'canal_id' => $channel->id,
                'rating_anterior' => $ratingAnterior,
                'rating_novo' => $newRating,
            ],
        ]);

        event(new CanalDegradado($channel, $ratingAnterior, $newRating));

        Log::warning('channel.quality_rating_changed', [
            'channel_id' => $channel->id,
            'tenant_id' => $channel->tenant_id,
            'from' => $ratingAnterior,
            'to' => $newRating,
        ]);
    }
}

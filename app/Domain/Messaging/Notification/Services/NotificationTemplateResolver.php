<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Services;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Models\NotificationTemplate;

/**
 * Feature 013 — Resolve o template configurado pelo tenant para um tipo de
 * notificação, aplicando o gate de aprovação do Princípio VI (D1).
 *
 * `resolve()` só retorna um template se:
 *   1. existir `NotificationTemplate` ativo do tenant para (tipo, canal); E
 *   2. existir `ChannelTemplate` correspondente com `meta_template_status='approved'`
 *      no canal — consulta runtime do status real de aprovação (Princípio VI).
 *
 * @see specs/013-outbound-notifications/research.md §R3, §R6
 */
final class NotificationTemplateResolver
{
    /**
     * Allow-list de chaves não-clínicas para `variables_map` (gate LGPD — R9).
     *
     * @var list<string>
     */
    public const ALLOWED_VARIABLES = [
        'patient_name',
        'appointment_datetime',
        'professional_name',
        'clinic_name',
        'days_until_expiry',
        'offer_expires_at',
    ];

    public function resolve(int $tenantId, NotificationType $type, Channel $channel): ?NotificationTemplate
    {
        /** @var NotificationTemplate|null $template */
        $template = NotificationTemplate::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('notification_type', $type->value)
            ->where('channel_type', $channel->type)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return null;
        }

        // Gate de aprovação Princípio VI (D1): consulta o status real no provedor.
        $approved = ChannelTemplate::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('channel_id', $channel->id)
            ->where('provider_template_id', $template->provider_template_id)
            ->where('meta_template_status', 'approved')
            ->exists();

        return $approved ? $template : null;
    }

    /**
     * Valida que todas as chaves de `variables_map` estão na allow-list não-clínica.
     *
     * @param array<string, mixed> $variablesMap
     */
    public static function variablesAreAllowed(array $variablesMap): bool
    {
        foreach (array_keys($variablesMap) as $key) {
            if (! in_array($key, self::ALLOWED_VARIABLES, true)) {
                return false;
            }
        }

        return true;
    }
}

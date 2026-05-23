<?php

declare(strict_types=1);

namespace App\Support\AuditLog;

use App\Models\AuditLog;

/**
 * **T042 (Fase 10 — Spec 010 / US-4)** — Humanização de eventos de audit
 * log para timeline de atividade recente.
 *
 * Regras LGPD (FR-019 / Q LGPD):
 *   - Descrições NUNCA incluem CPF, telefone completo, email completo ou
 *     conteúdo clínico.
 *   - Apenas nomes do ator e do recurso (já visíveis em telas individuais).
 *   - Allow-list de event types em `config/panel.recent_activity_allowlist`.
 *     Eventos como `paciente.viewed` (visualização de prontuário) NÃO entram.
 *
 * @see specs/010-dashboard-home/research.md R7
 */
final class Humanizer
{
    /**
     * Mapeia event_type → i18n key + retorna descrição renderizada.
     *
     * @return array{description: string, link: string|null}
     */
    public static function humanize(AuditLog $event): array
    {
        $actor = $event->user?->name ?? 'Sistema';
        $targetName = self::extractTargetName($event);
        $key = self::eventKey($event->action);

        $description = $key !== null
            ? __("panel.activity.{$key}", ['actor' => $actor, 'target' => $targetName ?? '—'])
            : "{$actor} executou {$event->action}";

        $link = self::resolveLink($event);

        return ['description' => $description, 'link' => $link];
    }

    public static function isAllowed(string $action): bool
    {
        $allowlist = config('panel.recent_activity_allowlist', []);

        return in_array($action, $allowlist, true);
    }

    private static function eventKey(string $action): ?string
    {
        // 'paciente.created' → 'paciente_created'
        $allowed = config('panel.recent_activity_allowlist', []);
        if (! in_array($action, $allowed, true)) {
            return null;
        }

        return str_replace('.', '_', $action);
    }

    private static function extractTargetName(AuditLog $event): ?string
    {
        // payload pode trazer 'name', 'nome', 'patient_name' etc.
        $payload = is_array($event->payload) ? $event->payload : [];

        foreach (['nome', 'name', 'patient_name', 'target_name', 'title'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    private static function resolveLink(AuditLog $event): ?string
    {
        if (! $event->auditable_type || ! $event->auditable_id) {
            return null;
        }

        return match (true) {
            str_ends_with($event->auditable_type, '\\Paciente') => "/panel/pacientes/{$event->auditable_id}",
            str_ends_with($event->auditable_type, '\\Appointment') => '/panel/agenda',
            str_ends_with($event->auditable_type, '\\Prescription') => "/panel/receituarios/{$event->auditable_id}",
            str_ends_with($event->auditable_type, '\\Conversation') => "/panel/inbox/conversa/{$event->auditable_id}",
            default => null,
        };
    }
}

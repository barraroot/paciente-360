<?php

declare(strict_types=1);

namespace App\Services\Panel\Collectors;

use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Agenda\ConfirmationDispatch;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Panel\PanelHomePolicy;
use App\Services\Panel\DataObjects\AttentionItemDto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * **T035 (Fase 10 — Spec 010 / US-3)** — Coleta alertas heterogêneos de
 * atenção (5 tipos), ordenados por severidade.
 *
 * Permission gates aplicados via {@see PanelHomePolicy}: tipos restritos
 * (webhook_dlq, confirmation_pending) só aparecem para users com a
 * permission correspondente. FR-013.
 *
 * @see specs/010-dashboard-home/research.md R6
 * @see specs/010-dashboard-home/data-model.md § 1.4
 */
final class AttentionItemsCollector
{
    public function __construct(private readonly PanelHomePolicy $policy) {}

    /**
     * @return Collection<int, AttentionItemDto>
     */
    public function collect(Tenant $tenant, User $user, string $scope): Collection
    {
        $items = collect();

        $items = $items->concat($this->conversationEscalated($user, $scope));
        $items = $items->concat($this->prescriptionExpiring($user, $scope));
        $items = $items->concat($this->pacienteFunilStale($user, $scope));

        if ($this->policy->canSeeConfirmationAlerts($user)) {
            $items = $items->concat($this->confirmationPending($user, $scope));
        }

        if ($this->policy->canSeeWebhookDlqAlerts($user)) {
            $items = $items->concat($this->webhookDlq());
        }

        return $items
            ->sortBy([
                fn (AttentionItemDto $a, AttentionItemDto $b) => $b->severityRank() <=> $a->severityRank(),
                fn (AttentionItemDto $a, AttentionItemDto $b) => $b->occurredAt <=> $a->occurredAt,
            ])
            ->take(config('panel.limits.attention_items', 5))
            ->values();
    }

    /**
     * @return Collection<int, AttentionItemDto>
     */
    private function conversationEscalated(User $user, string $scope): Collection
    {
        $threshold = Carbon::now()->subMinutes((int) config('panel.attention.conversation_escalated_minutes', 10));

        // Conversation não tem `ai_status='escalated'` — proxy: status='pendente'
        // significa "aguardando atendimento humano" (transitada por IA ou regra).
        // Filtro `updated_at < threshold` = pendente há mais de N minutos.
        $query = Conversation::query()
            ->with('paciente')
            ->where('status', 'pendente')
            ->where('updated_at', '<', $threshold);

        if ($scope === 'user') {
            $query->where(function ($q) use ($user) {
                $q->whereNull('assigned_user_id')->orWhere('assigned_user_id', $user->id);
            });
        }

        return $query->limit(5)->get()->map(fn (Conversation $c) => new AttentionItemDto(
            type: 'conversation_escalated',
            severity: 'danger',
            titleKey: 'panel.attention.conversation_escalated.title',
            description: __('panel.attention.conversation_escalated.description', [
                'patient' => $c->paciente?->nome ?? '—',
                'minutes' => $c->updated_at?->diffInMinutes(now()) ?? 0,
            ]),
            link: "/panel/inbox/conversa/{$c->id}",
            occurredAt: $c->updated_at ?? Carbon::now(),
        ));
    }

    /**
     * @return Collection<int, AttentionItemDto>
     */
    private function prescriptionExpiring(User $user, string $scope): Collection
    {
        $today = Carbon::today();
        $threshold = (clone $today)->addDays((int) config('panel.attention.prescription_expiring_days', 7));

        $query = Prescription::query()
            ->with('paciente')
            ->where('status', 'active')
            ->whereBetween('expires_at', [$today, $threshold]);

        if ($scope === 'user') {
            $query->where('professional_id', $user->id);
        }

        return $query->limit(5)->get()->map(fn (Prescription $p) => new AttentionItemDto(
            type: 'prescription_expiring',
            severity: 'danger',
            titleKey: 'panel.attention.prescription_expiring.title',
            description: trans_choice('panel.attention.prescription_expiring.description', max(1, $p->expires_at?->diffInDays(now()) ?? 1), [
                'patient' => $p->paciente?->nome ?? '—',
                'days' => $p->expires_at?->diffInDays(now()) ?? 0,
            ]),
            link: "/panel/receituarios/{$p->id}",
            occurredAt: $p->expires_at ?? Carbon::now(),
        ));
    }

    /**
     * @return Collection<int, AttentionItemDto>
     */
    private function pacienteFunilStale(User $user, string $scope): Collection
    {
        $threshold = Carbon::now()->subHours((int) config('panel.attention.paciente_funil_stale_hours', 48));

        // Q3: filtra estágios ativos não-terminais via funilColuna.is_terminal=false
        $query = Paciente::query()
            ->with(['funilColuna'])
            ->whereHas('funilColuna', function ($q) {
                $q->where('is_terminal', false);
            })
            ->where('updated_at', '<', $threshold);

        if ($scope === 'user') {
            $ids = Professional::query()->where('user_id', $user->id)->pluck('id')->all();
            $query->whereIn('profissional_responsavel_id', $ids ?: [-1]);
        }

        return $query->limit(5)->get()->map(fn (Paciente $p) => new AttentionItemDto(
            type: 'paciente_funil_stale',
            severity: 'warn',
            titleKey: 'panel.attention.paciente_funil_stale.title',
            description: __('panel.attention.paciente_funil_stale.description', [
                'patient' => $p->nome,
                'stage' => $p->funilColuna?->nome ?? '—',
                'hours' => $p->updated_at?->diffInHours(now()) ?? 0,
            ]),
            link: "/panel/pacientes/{$p->id}",
            occurredAt: $p->updated_at ?? Carbon::now(),
        ));
    }

    /**
     * @return Collection<int, AttentionItemDto>
     */
    private function confirmationPending(User $user, string $scope): Collection
    {
        $query = ConfirmationDispatch::query()
            ->with(['appointment.paciente'])
            ->where('status', 'pending_manual');

        if ($scope === 'user') {
            $ids = Professional::query()->where('user_id', $user->id)->pluck('id')->all();
            $query->whereHas('appointment', function ($q) use ($ids) {
                $q->whereIn('professional_id', $ids ?: [-1]);
            });
        }

        return $query->limit(5)->get()->map(function (ConfirmationDispatch $d) {
            $appt = $d->appointment;

            return new AttentionItemDto(
                type: 'confirmation_pending',
                severity: 'warn',
                titleKey: 'panel.attention.confirmation_pending.title',
                description: __('panel.attention.confirmation_pending.description', [
                    'patient' => $appt?->paciente?->nome ?? '—',
                    'time' => $appt?->starts_at?->format('d/m H:i') ?? '—',
                ]),
                link: '/panel/agenda',
                occurredAt: $d->updated_at ?? Carbon::now(),
            );
        });
    }

    /**
     * @return Collection<int, AttentionItemDto>
     */
    private function webhookDlq(): Collection
    {
        $threshold = Carbon::now()->subHours((int) config('panel.attention.webhook_dlq_lookback_hours', 24));

        // WebhookDelivery em DLQ — status = 'dead_letter'
        $query = WebhookDelivery::query()
            ->with('webhookEndpoint')
            ->where('status', 'dead_letter')
            ->where('updated_at', '>=', $threshold);

        return $query->limit(5)->get()->map(function (WebhookDelivery $w) {
            return new AttentionItemDto(
                type: 'webhook_dlq',
                severity: 'info',
                titleKey: 'panel.attention.webhook_dlq.title',
                description: __('panel.attention.webhook_dlq.description', [
                    'event_type' => $w->event_type ?? '—',
                    'endpoint' => $w->webhookEndpoint?->url ?? '—',
                ]),
                link: '/panel/integracoes/webhooks/dlq',
                occurredAt: $w->updated_at ?? Carbon::now(),
            );
        });
    }
}

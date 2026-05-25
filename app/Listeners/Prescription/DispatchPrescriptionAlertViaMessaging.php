<?php

namespace App\Listeners\Prescription;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;
use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\AlertType;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Preferences\PatientProfessionalPreference;
use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\ReceitaProximaDoVencimento;
use App\Support\Metrics\PrescriptionMetricsContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * T100 — Despacha alerta de vencimento via canal de mensageria do tenant.
 *
 * Ordem de verificação:
 *  1. Opt-out paciente → skipped (recipient_opted_out).
 *  2. Debounce 4h por (patient_id, alert_type) → skipped (debounced).
 *  3. Canal WhatsApp ativo → blocked_no_channel se ausente.
 *  4. Template HSM aprovado → blocked_no_template se ausente.
 *  5. Envia via MessageDispatchService → dispatched ou failed.
 *
 * IMPORTANTE: Não registrar manualmente em AppServiceProvider.
 * Laravel 13 auto-descobre via type-hint do método handle().
 *
 * Uso público `handleWithAlert()` permite testes granulares sem precisar
 * de infraestrutura de filas/cron completa.
 */
class DispatchPrescriptionAlertViaMessaging
{
    public function __construct(
        private readonly PrescriptionMetricsContract $metrics,
    ) {}

    /**
     * Entry point auto-discovered: aciona despacho via canal de mensageria.
     *
     * Busca o alert pendente associado à prescription + daysUntilExpiry.
     */
    public function handle(ReceitaProximaDoVencimento $event): void
    {
        $alertType = AlertType::fromDays($event->daysUntilExpiry);

        if ($alertType === null) {
            Log::warning('prescription.alert.unknown_days', [
                'prescription_id' => $event->prescriptionId,
                'days_until_expiry' => $event->daysUntilExpiry,
            ]);

            return;
        }

        $alert = PrescriptionAlert::withoutTenantScope()
            ->where('prescription_id', $event->prescriptionId)
            ->where('alert_type', $alertType)
            ->where('status', AlertStatus::Pending)
            ->first();

        if ($alert === null) {
            Log::debug('prescription.alert.no_pending_alert', [
                'prescription_id' => $event->prescriptionId,
                'alert_type' => $alertType->value,
            ]);

            return;
        }

        $this->processForAlert($event, $alert);
    }

    /**
     * Processa o despacho para um alerta específico.
     * Exposto publicamente para testes granulares.
     *
     * NOTA: o prefixo "processFor" evita que o auto-discovery do Laravel 13
     * detecte este método como listener, pois apenas métodos `handle*` são
     * descobertos automaticamente. (Veja CLAUDE.md §5 Agendamento Fase 5.)
     */
    public function processForAlert(ReceitaProximaDoVencimento $event, PrescriptionAlert $alert): void
    {
        $prescription = Prescription::withoutGlobalScopes()->find($event->prescriptionId);

        if ($prescription === null) {
            return;
        }

        $tenantId = $prescription->tenant_id;

        // --- Sentry tags (T114) ---
        try {
            if (function_exists('\Sentry\configureScope')) {
                \Sentry\configureScope(function ($scope) use ($alert, $prescription): void {
                    $scope->setTag('prescription.id', (string) $prescription->id);
                    $scope->setTag('prescription.type', $prescription->type->value);
                    $scope->setTag('alert.type', $alert->alert_type->value);
                });
            }
        } catch (\Throwable) {
            // Sentry ausente em testes — não bloquear
        }

        // --- 1. Opt-out paciente (T121) ---
        $pref = PatientProfessionalPreference::withoutTenantScope()
            ->where('patient_id', $prescription->patient_id)
            ->where('professional_id', $prescription->professional_id)
            ->first();

        if ($pref?->suppress_renewal_notifications === true) {
            $alert->update([
                'status' => AlertStatus::Skipped,
                'skip_reason' => 'recipient_opted_out',
            ]);

            $this->metrics->alertBlocked('recipient_opted_out', $tenantId);

            Log::info('prescription.alert.opted_out', [
                'prescription_id' => $prescription->id,
                'patient_id' => $prescription->patient_id,
                'alert_type' => $alert->alert_type->value,
            ]);

            return;
        }

        // --- 2. Debounce 4h por destinatário (T120) ---
        $debounceKey = "messaging_debounce:prescription_alert:{$prescription->patient_id}:{$alert->alert_type->value}";
        $isDebounced = Redis::set($debounceKey, 1, 'EX', 14400, 'NX') === false;

        if ($isDebounced) {
            $alert->update([
                'status' => AlertStatus::Skipped,
                'skip_reason' => 'debounced',
            ]);

            $this->metrics->alertBlocked('debounced', $tenantId);

            Log::info('prescription.alert.debounced', [
                'prescription_id' => $prescription->id,
                'patient_id' => $prescription->patient_id,
                'alert_type' => $alert->alert_type->value,
            ]);

            return;
        }

        // --- 3. Canal WhatsApp ativo ---
        $channel = Channel::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('type', 'whatsapp')
            ->where('status', 'ativo')
            ->first();

        if ($channel === null) {
            $alert->update(['status' => AlertStatus::BlockedNoChannel]);

            $this->metrics->alertBlocked('no_channel', $tenantId);

            // T119 — Fallback Inbox (DEFERRED: sem tabela inbox_tasks no escopo)
            Log::warning('prescription.alert.blocked', [
                'reason' => 'no_channel',
                'prescription_id' => $prescription->id,
                'alert_type' => $alert->alert_type->value,
                'tenant_id' => $tenantId,
                // TODO: integração futura com Inbox Fase 3 para criar tarefa
            ]);

            return;
        }

        // --- 4. Template HSM aprovado ---
        $templateName = "prescription.expiry_warning_{$alert->alert_type->value}";
        $template = ChannelTemplate::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('channel_id', $channel->id)
            ->where('meta_template_name', $templateName)
            ->where('meta_template_status', 'approved')
            ->first();

        if ($template === null) {
            $alert->update(['status' => AlertStatus::BlockedNoTemplate]);

            $this->metrics->alertBlocked('no_template', $tenantId);

            // T119 — Fallback Inbox (DEFERRED)
            Log::warning('prescription.alert.blocked', [
                'reason' => 'no_template',
                'template_name' => $templateName,
                'prescription_id' => $prescription->id,
                'alert_type' => $alert->alert_type->value,
                'tenant_id' => $tenantId,
                // TODO: integração futura com Inbox Fase 3 para criar tarefa
            ]);

            return;
        }

        // --- 5. Envia via OutboundNotificationDispatcher (feature 013) ---
        // Entrega REAL + rastreio em outbound_notifications. O dispatcher resolve
        // canal/conversa, aplica o gate de template aprovado (Princípio VI) e,
        // se não entregável, roteia para contato manual (mensagem de sistema).
        try {
            app(OutboundNotificationDispatcher::class)->dispatch(new NotificationRequest(
                tenantId: $tenantId,
                patientId: $prescription->patient_id,
                type: NotificationType::PrescriptionExpiryAlert,
                milestone: "d_{$event->daysUntilExpiry}",
                sourceType: Prescription::class,
                sourceId: $prescription->id,
                context: ['days_until_expiry' => $event->daysUntilExpiry],
                professionalId: $prescription->professional_id,
            ));

            $alert->update([
                'status' => AlertStatus::Dispatched,
                'dispatched_at' => now(),
                'channel_id' => $channel->id,
            ]);

            $this->metrics->alertDispatched($tenantId, $alert->alert_type->value, 'dispatched');

            Log::info('prescription.alert.dispatched', [
                'prescription_id' => $prescription->id,
                'alert_type' => $alert->alert_type->value,
                'channel_id' => $channel->id,
                'template_name' => $templateName,
            ]);
        } catch (\Throwable $e) {
            $alert->update([
                'status' => AlertStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            $this->metrics->alertDispatched($tenantId, $alert->alert_type->value, 'failed');

            Log::error('prescription.alert.dispatch_failed', [
                'prescription_id' => $prescription->id,
                'alert_type' => $alert->alert_type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

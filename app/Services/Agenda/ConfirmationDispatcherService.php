<?php

namespace App\Services\Agenda;

use App\Events\Agenda\ConsultaConfirmacaoPendente;
use App\Events\Agenda\ConsultaPendenteContatoManual;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\ConfirmationDispatch;
use App\Support\IanaTimezoneCity;
use Illuminate\Support\Carbon;

/**
 * T104 — Dispatcher de confirmações automáticas (US-6.4 / clarify nº 6).
 *
 * Cron `agenda:dispatch-confirmations` (everyFiveMinutes) chama dispatchPending()
 * que varre Appointments elegíveis e cria ConfirmationDispatch + emite eventos.
 *
 * Kinds:
 *  - 24h                       → entre T-24h e T-23h (janela de 1h para o cron pegar)
 *  - 2h                        → entre T-2h e T-1h45 (após confirmação 24h ainda não respondida)
 *  - retry_30min               → entre T-30min e T-15min (se T-24h teve não-resposta)
 *  - 15min_manual_escalation   → após T-15min (escala manual via Fase 3 inbox)
 */
final class ConfirmationDispatcherService
{
    private readonly TimezoneResolverService $tzResolver;

    public function __construct(?TimezoneResolverService $tzResolver = null)
    {
        $this->tzResolver = $tzResolver ?? app(TimezoneResolverService::class);
    }

    /**
     * @return array<string, int> contagem por kind disparada
     */
    public function dispatchPending(?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $stats = ['24h' => 0, '2h' => 0, 'retry_30min' => 0, '15min_manual_escalation' => 0];

        // Janelas de busca
        $windows = [
            '24h' => [$now->copy()->addHours(23), $now->copy()->addHours(24)],
            '2h' => [$now->copy()->addMinutes(105), $now->copy()->addHours(2)],
            'retry_30min' => [$now->copy()->addMinutes(15), $now->copy()->addMinutes(30)],
            '15min_manual_escalation' => [$now->copy(), $now->copy()->addMinutes(15)],
        ];

        foreach ($windows as $kind => [$lo, $hi]) {
            $candidates = Appointment::query()
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->whereBetween('starts_at', [$lo, $hi])
                ->whereDoesntHave('confirmationDispatches', fn ($q) => $q->where('kind', $kind))
                ->get();

            foreach ($candidates as $appointment) {
                if ($this->shouldSkip($appointment, $kind)) {
                    continue;
                }

                $this->dispatchOne($appointment, $kind);
                $stats[$kind]++;
            }
        }

        return $stats;
    }

    private function shouldSkip(Appointment $appointment, string $kind): bool
    {
        // 2h e retry_30min só rodam se ainda não confirmada
        if (in_array($kind, ['2h', 'retry_30min'], true) && $appointment->status === 'confirmed') {
            return true;
        }

        // retry_30min só se houve não-resposta no 24h
        if ($kind === 'retry_30min') {
            $had24h = $appointment->confirmationDispatches()
                ->where('kind', '24h')
                ->whereNull('response_value')
                ->exists();
            if (! $had24h) {
                return true;
            }
        }

        // 15min_manual_escalation só se retry_30min não respondido
        if ($kind === '15min_manual_escalation') {
            $hadRetry = $appointment->confirmationDispatches()
                ->where('kind', 'retry_30min')
                ->whereNull('response_value')
                ->exists();
            if (! $hadRetry) {
                return true;
            }
        }

        return false;
    }

    private function dispatchOne(Appointment $appointment, string $kind): void
    {
        $tz = $this->tzResolver->forTenant($appointment->tenant);
        $startsAtLocal = $appointment->starts_at->copy()->setTimezone($tz);
        $horario = $startsAtLocal->format('H:i');
        $tzLabel = IanaTimezoneCity::canonicalLabel($tz);

        // Detecta se paciente sem canal — cria pending_manual diretamente (FR-024).
        // Proxy MVP: telefone_primario preenchido (sem ele Fase 3 não consegue enviar
        // mensagem por nenhum canal — WhatsApp/Instagram/widget exige identificação).
        $hasCanal = ! empty($appointment->paciente?->telefone_primario);

        if (! $hasCanal && $kind === '24h') {
            ConfirmationDispatch::create([
                'tenant_id' => $appointment->tenant_id,
                'appointment_id' => $appointment->id,
                'kind' => $kind,
                'via_ia' => false,
                'dispatched_at' => now(),
                'status' => 'pending_manual',
            ]);
            ConsultaPendenteContatoManual::dispatch($appointment, [$kind], 'no_channel');

            return;
        }

        // Detecta se paciente tem conversa ativa com IA (Fase 3)
        // Por enquanto: assume que quando channel_origin === 'ia' tem conversa IA ativa.
        $viaIa = $appointment->channel_origin === 'ia';

        // 15min_manual_escalation = pending_manual direto, sem template
        if ($kind === '15min_manual_escalation') {
            ConfirmationDispatch::create([
                'tenant_id' => $appointment->tenant_id,
                'appointment_id' => $appointment->id,
                'kind' => $kind,
                'via_ia' => $viaIa,
                'dispatched_at' => now(),
                'status' => 'pending_manual',
            ]);

            $previousAttempts = $appointment->confirmationDispatches()
                ->whereIn('kind', ['24h', 'retry_30min'])
                ->pluck('kind')
                ->toArray();

            ConsultaPendenteContatoManual::dispatch($appointment, $previousAttempts, 'no_response');

            return;
        }

        // Cria dispatch + emite evento (Fase 3 envia mensagem)
        ConfirmationDispatch::create([
            'tenant_id' => $appointment->tenant_id,
            'appointment_id' => $appointment->id,
            'kind' => $kind,
            'via_ia' => $viaIa,
            'dispatched_at' => now(),
            'status' => 'dispatched',
        ]);

        ConsultaConfirmacaoPendente::dispatch(
            $appointment,
            $kind,
            $viaIa,
            $horario,
            $tzLabel,
        );
    }
}

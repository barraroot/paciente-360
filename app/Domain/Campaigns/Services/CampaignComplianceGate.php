<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\CampaignTemplateMeta;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **T144 (Fase 8 — Lote C US-9.3)** — Coração do Princípio VI (Conformidade Meta).
 *
 * Aplica 4 validações sequenciais em runtime ANTES de cada envio individual
 * de campanha. **Gate 1 constitucional** — toda mensagem de campanha
 * não-transacional passa por aqui. Falha em qualquer checagem bloqueia
 * o envio com motivo auditável.
 *
 * Validações (AC-9.1.2 / AC-9.3.1):
 *   1. Opt-in MARKETING válido (Q24/Q25) — ConsentService::hasGranted()
 *   2. Template aprovado pela Meta + has_unsubscribe true (AC-9.3.3)
 *   3. Dentro do horário comercial do tenant (Q7) — graceful (skip se não configurado)
 *   4. Limite diário do plano não excedido (Q2) — `Tenant::dailyCampaignLimit()`
 *
 * Validações adicionais que o dispatcher consulta separadamente:
 *   - `/sair` recebido últimas 24h (verificado pelo Listener de marketing revoked)
 *   - Canal alvo conectado (verificado pelo audience calculator)
 *
 * **Cooldown opcional**: gate puro (sem side-effect) — apenas retorna
 * ComplianceResult. Dispatcher é responsável por persistir blocked_reason.
 */
final class CampaignComplianceGate
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly MetaTemplateStatusChecker $templateChecker,
    ) {}

    /**
     * Avalia se um envio específico (campanha × paciente × tenant) é permitido.
     *
     * @param  int  $alreadyDispatchedToday  contagem corrente de envios HOJE
     *                                        para esta campanha — passado pelo
     *                                        dispatcher (evita query duplicada).
     */
    public function evaluate(
        Paciente $patient,
        Tenant $tenant,
        ?CampaignTemplateMeta $templateMeta,
        int $alreadyDispatchedToday,
    ): ComplianceResult {
        // 1. Opt-in MARKETING válido (Q25).
        if (! $this->consentService->hasGranted($patient->id, ConsentFinalidade::Marketing)) {
            return ComplianceResult::blocked('no_marketing_opt_in', "patient_id={$patient->id}");
        }

        // 2. Template aprovado + has_unsubscribe (AC-9.3.3).
        if ($templateMeta === null) {
            return ComplianceResult::blocked('no_template_approved', 'template ausente');
        }
        $remoteStatus = $this->templateChecker->checkStatus($templateMeta);
        if ($remoteStatus !== 'approved') {
            return ComplianceResult::blocked('no_template_approved', "meta_status={$remoteStatus}");
        }
        if (! $templateMeta->has_unsubscribe) {
            return ComplianceResult::blocked('no_template_approved', 'has_unsubscribe=false');
        }

        // 3. Horário comercial do tenant (Q7).
        if (! $this->isWithinBusinessHours($tenant, Carbon::now())) {
            return ComplianceResult::blocked('outside_business_hours');
        }

        // 4. Limite diário do plano (Q2).
        $dailyLimit = $tenant->dailyCampaignLimit();
        if ($alreadyDispatchedToday >= $dailyLimit) {
            return ComplianceResult::blocked(
                'daily_limit_exceeded',
                "dispatched={$alreadyDispatchedToday}, limit={$dailyLimit}",
            );
        }

        return ComplianceResult::passed();
    }

    /**
     * Verifica se `now` está dentro do horário comercial do tenant.
     *
     * **Graceful degradation**: tenant sem `settings.business_hours` configurado
     * retorna TRUE (todos os horários permitidos) — não bloqueia tenant sem
     * configuração. Tenant DEVE configurar via Fase 5 antes de operar
     * campanhas em produção.
     *
     * Estrutura esperada de `settings.business_hours`:
     *   {monday:"08:00-18:00", tuesday:"08:00-18:00", ..., timezone:"America/Sao_Paulo"}
     */
    public function isWithinBusinessHours(Tenant $tenant, Carbon $now): bool
    {
        $hours = data_get($tenant->settings ?? [], 'business_hours');

        if (! is_array($hours) || $hours === []) {
            return true; // Sem configuração — graceful permit.
        }

        $timezone = $hours['timezone'] ?? 'America/Sao_Paulo';
        try {
            $localNow = $now->copy()->setTimezone($timezone);
        } catch (\Throwable $e) {
            return true;
        }

        $dayKey = strtolower($localNow->englishDayOfWeek);
        $range = $hours[$dayKey] ?? null;

        if (! is_string($range) || ! preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $range, $m)) {
            // Dia ausente do hash — assumir fechado (sábado/domingo típicos).
            return false;
        }

        $startMinutes = ((int) $m[1]) * 60 + (int) $m[2];
        $endMinutes = ((int) $m[3]) * 60 + (int) $m[4];
        $nowMinutes = $localNow->hour * 60 + $localNow->minute;

        return $nowMinutes >= $startMinutes && $nowMinutes < $endMinutes;
    }

    /**
     * Verifica se paciente enviou `/sair` nas últimas 24h (cooldown adicional —
     * Q AC-9.3.1 #4). Usado pelo dispatcher complementar à checagem de opt-in
     * (caso tenha havido race entre revogação e enfileiramento).
     */
    public function hasReceivedSairCommandRecently(int $patientId, int $hours = 24): bool
    {
        $cutoff = Carbon::now()->subHours($hours);

        return DB::table('consent_records')
            ->where('patient_id', $patientId)
            ->where('finalidade', 'marketing')
            ->where('state', 'revoked')
            ->where('revoked_at', '>=', $cutoff)
            ->exists();
    }
}

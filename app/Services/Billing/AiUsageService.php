<?php

namespace App\Services\Billing;

use App\Events\Billing\HardCapTriggered;
use App\Jobs\Billing\AlertAiUsageThresholdJob;
use App\Models\AiUsageMeter;
use App\Models\Plan;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\HardCapTriggeredNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Serviço de medição de uso de IA por tenant (T222).
 *
 * Responsabilidades:
 *  - `getCurrentMeter()`: busca/cria meter para o mês atual em BRT.
 *  - `buildResource()`: monta o shape de `AiUsageResource` com projeção e overage.
 *  - `recordUsage()`: incremento atômico via SQL UPDATE; verifica hard_cap e thresholds.
 *
 * Timezone: todo cálculo de `year_month` usa `America/Sao_Paulo` (BRT/BRST)
 * conforme constituição § Localização.
 *
 * LGPD: nenhum dado de conteúdo de mensagem é armazenado — apenas counts.
 */
final class AiUsageService
{
    private const BRT = 'America/Sao_Paulo';

    public function getCurrentMeter(Tenant $tenant): AiUsageMeter
    {
        $yearMonth = Carbon::now(self::BRT)->format('Y-m');

        /** @var AiUsageMeter */
        return AiUsageMeter::withoutGlobalScope(TenantScope::class)
            ->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'year_month' => $yearMonth,
                ],
                [
                    'messages_count' => 0,
                    'included_quota_snapshot' => $this->resolveIncludedQuota($tenant),
                    'overage_count' => 0,
                    'last_reset_at' => now(),
                ]
            );
    }

    /**
     * Resolve a cota incluída do plano a partir do `plan_snapshot` da
     * assinatura ativa. Retorna 0 quando não há assinatura (trial sem sub).
     *
     * Decisão: trial sem sub = cota 0 (acesso conservador; upgrade CTA
     * empurra o usuário a contratar um plano).
     */
    public function resolveIncludedQuota(Tenant $tenant): int
    {
        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $tenant->id)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereNull('ends_at')
            ->orderByDesc('created_at')
            ->first();

        if ($subscription === null) {
            return 0;
        }

        $snapshot = is_string($subscription->plan_snapshot)
            ? json_decode($subscription->plan_snapshot, true)
            : (array) $subscription->plan_snapshot;

        return (int) ($snapshot['included_ai_messages'] ?? 0);
    }

    /**
     * Monta o shape do `AiUsageResource` com todos os campos calculados.
     *
     * @return array<string, mixed>
     */
    public function buildResource(AiUsageMeter $meter): array
    {
        $now = Carbon::now(self::BRT);
        $cycleStart = Carbon::parse($meter->year_month.'-01', self::BRT)->startOfDay();
        $cycleEnd = $cycleStart->copy()->endOfMonth()->endOfDay();

        $daysInMonth = $cycleStart->daysInMonth;
        $dayOfMonth = $now->day;

        // Projeção linear: (consumed / daysElapsed) * daysInMonth.
        $projected = $dayOfMonth > 0
            ? (int) round($meter->messages_count * $daysInMonth / $dayOfMonth)
            : 0;

        $overage = max(0, $meter->messages_count - $meter->included_quota_snapshot);

        // Custo de excedente: busca do plano via tenant para obter overage_price_cents.
        $overageCostCents = 0;

        if ($overage > 0) {
            $overagePriceCents = $this->resolveOveragePriceCents($meter->tenant_id);
            $overageCostCents = $overage * $overagePriceCents;
        }

        return [
            'year_month' => $meter->year_month,
            'included_quota' => $meter->included_quota_snapshot,
            'consumed' => $meter->messages_count,
            'projected_end_of_month' => $projected,
            'overage' => $overage,
            'overage_cost_estimate_cents' => $overageCostCents,
            'hard_cap' => $meter->hard_cap,
            'hard_cap_triggered_at' => $meter->hard_cap_triggered_at?->toIso8601String(),
            'cycle_start' => $cycleStart->toIso8601String(),
            'cycle_end' => $cycleEnd->toIso8601String(),
        ];
    }

    /**
     * Incrementa atomicamente o contador de mensagens.
     *
     * Usa SQL UPDATE direto para garantir atomicidade em concorrência
     * (evita race condition de read-modify-write do Eloquent).
     * Após increment: verifica hard_cap e despacha alertas de threshold.
     */
    public function recordUsage(Tenant $tenant, int $messages = 1): AiUsageMeter
    {
        $meter = $this->getCurrentMeter($tenant);

        $beforeCount = $meter->messages_count;

        DB::statement(
            'UPDATE ai_usage_meters SET messages_count = messages_count + ? WHERE id = ?',
            [$messages, $meter->id]
        );

        $meter->refresh();

        $afterCount = $meter->messages_count;

        // Verifica hard cap.
        if (
            $meter->hard_cap !== null
            && $afterCount >= $meter->hard_cap
            && $meter->hard_cap_triggered_at === null
        ) {
            $this->triggerHardCap($meter, $tenant);
            $meter->refresh();
        }

        // Verifica thresholds de alerta (80% e 100%) — apenas na primeira vez que cruzam.
        if ($meter->included_quota_snapshot > 0) {
            $beforePct = $meter->included_quota_snapshot > 0
                ? ($beforeCount / $meter->included_quota_snapshot) * 100
                : 0;

            $afterPct = ($afterCount / $meter->included_quota_snapshot) * 100;

            foreach ([80, 100] as $threshold) {
                if ($beforePct < $threshold && $afterPct >= $threshold) {
                    AlertAiUsageThresholdJob::dispatch($tenant->id, $threshold);
                }
            }
        }

        return $meter;
    }

    /**
     * Aciona o hard cap: marca `hard_cap_triggered_at` e dispara evento/notificação.
     * Chamado por `recordUsage` quando excede, ou por `HardCapService::setHardCap`
     * quando o cap é definido abaixo do consumo atual.
     */
    public function triggerHardCap(AiUsageMeter $meter, Tenant $tenant): void
    {
        $meter->hard_cap_triggered_at = now();
        $meter->save();

        Event::dispatch(
            new HardCapTriggered($tenant, $meter)
        );

        // Notifica Admin Clínica e Financeiro.
        $users = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin-clinica', 'financeiro']))
            ->get();

        foreach ($users as $user) {
            $user->notify(new HardCapTriggeredNotification($tenant, $meter));
        }
    }

    /**
     * Busca o `overage_price_cents` do plano ativo do tenant.
     * Retorna 0 se não houver assinatura ativa com snapshot de plano.
     */
    private function resolveOveragePriceCents(int $tenantId): int
    {
        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereNull('ends_at')
            ->orderByDesc('created_at')
            ->first();

        if ($subscription === null) {
            return 0;
        }

        $snapshot = is_string($subscription->plan_snapshot)
            ? json_decode($subscription->plan_snapshot, true)
            : (array) $subscription->plan_snapshot;

        // plan_snapshot pode não ter overage_price_cents — fallback para Plan direto.
        if (isset($snapshot['overage_price_cents'])) {
            return (int) $snapshot['overage_price_cents'];
        }

        $plan = Plan::find($subscription->plan_id ?? null);

        return $plan ? (int) $plan->overage_price_cents : 0;
    }
}

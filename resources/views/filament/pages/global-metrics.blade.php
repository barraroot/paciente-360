<x-filament-panels::page>
    @php
        $snapshot = $this->getViewData()['snapshot'];
        $mrr = number_format(($snapshot['mrr_cents'] ?? 0) / 100, 2, ',', '.');
        $arr = number_format(($snapshot['arr_cents'] ?? 0) / 100, 2, ',', '.');
        $churn = $snapshot['churn_primary']['rate_percent'] ?? 0;
        $revenueChurn = number_format(($snapshot['revenue_churn']['cancelled_mrr_lost_cents'] ?? 0) / 100, 2, ',', '.');
        $conv = $snapshot['trial_to_paid']['rate_percent'] ?? 0;
        $aiUsage = $snapshot['ai_usage_total_month'] ?? 0;
    @endphp

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">MRR</h3>
                <p class="mt-2 text-3xl font-bold">R$ {{ $mrr }}</p>
                <p class="mt-1 text-xs text-gray-500">Soma das mensalidades de tenants ativos</p>
            </div>

            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">ARR (estimativa)</h3>
                <p class="mt-2 text-3xl font-bold">R$ {{ $arr }}</p>
                <p class="mt-1 text-xs text-gray-500">MRR × 12</p>
            </div>

            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Tenants ativos</h3>
                <p class="mt-2 text-3xl font-bold">{{ $snapshot['tenants_active'] ?? 0 }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Churn rate (30d)</h3>
                <p class="mt-2 text-2xl font-bold">{{ $churn }}%</p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $snapshot['churn_primary']['cancelled'] ?? 0 }} cancelados /
                    {{ $snapshot['churn_primary']['denominator'] ?? 0 }} ativos início do período
                </p>
            </div>

            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Revenue churn (30d)</h3>
                <p class="mt-2 text-2xl font-bold">R$ {{ $revenueChurn }}</p>
                <p class="mt-1 text-xs text-gray-500">MRR perdido por cancelamentos</p>
            </div>

            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Conversão trial→pago (30d)</h3>
                <p class="mt-2 text-2xl font-bold">{{ $conv }}%</p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $snapshot['trial_to_paid']['trials_converted'] ?? 0 }} /
                    {{ $snapshot['trial_to_paid']['trials_started'] ?? 0 }} novos tenants
                </p>
            </div>
        </div>

        <div class="rounded border bg-white p-4 dark:bg-gray-800">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Consumo total de IA (mês corrente)</h3>
            <p class="mt-2 text-2xl font-bold">{{ number_format($aiUsage, 0, ',', '.') }} mensagens</p>
            <p class="mt-1 text-xs text-gray-500">Cross-tenant — custo de plataforma</p>
        </div>

        <p class="text-xs text-gray-500">
            Snapshot calculado em {{ $snapshot['computed_at'] ?? 'desconhecido' }}.
            Atualização automática a cada hora via cron `super-admin:compute-global-metrics`.
        </p>
    </div>
</x-filament-panels::page>

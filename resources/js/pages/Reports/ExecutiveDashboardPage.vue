<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useExecutiveDashboard } from '@/composables/useExecutiveDashboard.js';
import PeriodFilter from '@/components/Reports/PeriodFilter.vue';
import ExportMenu from '@/components/Reports/ExportMenu.vue';
import StaleDataBanner from '@/components/Reports/StaleDataBanner.vue';
import KpiCardWithSparkline from '@/components/Reports/KpiCardWithSparkline.vue';
import TopProceduresCard from '@/components/Reports/TopProceduresCard.vue';
import OccupancyByProfessionalCard from '@/components/Reports/OccupancyByProfessionalCard.vue';
import DashboardSkeleton from '@/components/Reports/DashboardSkeleton.vue';

const { t } = useI18n();
const {
    data,
    loading,
    error,
    exporting,
    window,
    setWindow,
    refresh,
    exportPdf,
} = useExecutiveDashboard();

const toast = ref(null);

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
}

const metrics = computed(() => data.value?.metrics ?? {});

function getValue(metricKey) {
    return metrics.value?.[metricKey]?.value ?? null;
}

function getDelta(metricKey) {
    return metrics.value?.[metricKey]?.delta_percent ?? null;
}

function getJson(metricKey) {
    return metrics.value?.[metricKey]?.json ?? null;
}

// Métricas com polaridade invertida (menos é melhor).
const inversePolarityMetrics = new Set(['no_show_rate', 'response_time_first_p95']);

const kpiCards = computed(() => [
    { key: 'leads_by_channel', label: t('executive_dashboard.metrics.leads_by_channel.label'), formatType: 'count' },
    { key: 'conversion_rate', label: t('executive_dashboard.metrics.conversion_rate.label'), formatType: 'percent' },
    { key: 'no_show_rate', label: t('executive_dashboard.metrics.no_show_rate.label'), formatType: 'percent' },
    { key: 'estimated_revenue', label: t('executive_dashboard.metrics.estimated_revenue.label'), formatType: 'currency_brl' },
    { key: 'response_time_first_p95', label: t('executive_dashboard.metrics.response_time_first_p95.label'), formatType: 'seconds' },
    { key: 'ai_autonomous_resolution_rate', label: t('executive_dashboard.metrics.ai_autonomous_resolution_rate.label'), formatType: 'percent' },
]);

const topProcedures = computed(() => getJson('top_procedure_types') ?? []);
const occupancy = computed(() => getJson('occupancy_by_professional') ?? []);
const aggregationLag = computed(() => data.value?.aggregation_lag_seconds ?? null);

const hasNoDataAtAll = computed(() => {
    if (loading.value || error.value) {
        return false;
    }
    if (! data.value || ! metrics.value) {
        return true;
    }
    const everyMetricNull = kpiCards.value.every((card) => getValue(card.key) === null);
    return everyMetricNull && topProcedures.value.length === 0 && occupancy.value.length === 0;
});

async function handleExportPdf() {
    const ok = await exportPdf();
    if (ok) {
        showToast(t('executive_dashboard.export.success'), 'success');
    } else {
        showToast(t('executive_dashboard.export.failed'), 'error');
    }
}
</script>

<template>
    <div class="p-4 sm:p-6 max-w-7xl mx-auto">
        <!-- Header -->
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    {{ t('executive_dashboard.page_title') }}
                </h1>
                <p class="mt-0.5 text-sm text-foreground-muted">
                    {{ t('executive_dashboard.page_subtitle') }}
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <PeriodFilter
                    :model-value="window"
                    @update:model-value="setWindow"
                />
                <ExportMenu :loading="exporting" @export-pdf="handleExportPdf" />
            </div>
        </header>

        <!-- Toast -->
        <div
            v-if="toast"
            role="status"
            aria-live="polite"
            class="fixed right-4 top-20 z-50 rounded-lg px-4 py-3 text-sm font-medium shadow-popover"
            :class="toast.type === 'success'
                ? 'border border-success-500 bg-success-50 text-success-700'
                : 'border border-danger-500 bg-danger-50 text-danger-700'"
        >
            {{ toast.message }}
        </div>

        <!-- Stale banner -->
        <StaleDataBanner :lag-seconds="aggregationLag" :window="window" />

        <!-- Global error banner -->
        <div
            v-if="error"
            role="alert"
            class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
        >
            <span>{{ t('executive_dashboard.errors.global') }}</span>
            <button
                type="button"
                class="font-medium underline hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 rounded"
                @click="refresh"
            >
                {{ t('executive_dashboard.errors.retry') }}
            </button>
        </div>

        <!-- Empty state (tenant novo sem dados) -->
        <div
            v-if="hasNoDataAtAll"
            class="flex flex-col items-center justify-center px-6 py-16 text-center"
        >
            <div class="inline-flex w-14 h-14 items-center justify-center rounded-full bg-primary-50 text-primary-700 mb-4">
                <span class="text-2xl" aria-hidden="true">📊</span>
            </div>
            <h2 class="text-xl font-semibold text-foreground mb-2">
                {{ t('executive_dashboard.empty_state.title') }}
            </h2>
            <p class="text-sm text-foreground-muted max-w-md">
                {{ t('executive_dashboard.empty_state.explanation') }}
            </p>
        </div>

        <!-- Loading skeleton (primeira carga) -->
        <DashboardSkeleton v-else-if="loading && !data" />

        <!-- Conteúdo principal -->
        <template v-else>
            <ul role="list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <li v-for="card in kpiCards" :key="card.key">
                    <KpiCardWithSparkline
                        :label="card.label"
                        :value="getValue(card.key)"
                        :format-type="card.formatType"
                        :delta-percent="getDelta(card.key)"
                        :inverse-polarity="inversePolarityMetrics.has(card.key)"
                        :loading="loading"
                        :error="error !== null && !data"
                    />
                </li>
            </ul>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <TopProceduresCard
                    :items="topProcedures"
                    :loading="loading"
                    :error="error !== null && !data"
                />
                <OccupancyByProfessionalCard
                    :items="occupancy"
                    :loading="loading"
                    :error="error !== null && !data"
                />
            </div>
        </template>
    </div>
</template>

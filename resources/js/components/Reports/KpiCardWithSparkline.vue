<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Sparkline from './Sparkline.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String, null], default: null },
    formatType: { type: String, default: 'count' }, // percent | currency_brl | seconds | count
    deltaPercent: { type: [Number, null], default: null },
    inversePolarity: { type: Boolean, default: false },
    sparklinePoints: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});

const { t } = useI18n();

const formattedValue = computed(() => {
    if (props.value === null || props.value === undefined) {
        return null;
    }
    const num = Number(props.value);
    if (! Number.isFinite(num)) {
        return null;
    }

    switch (props.formatType) {
        case 'percent':
            return `${num.toFixed(1)}%`;
        case 'currency_brl':
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(num);
        case 'seconds':
            return `${num.toFixed(1)}s`;
        case 'count':
        default:
            return new Intl.NumberFormat('pt-BR').format(Math.round(num));
    }
});

const hasNoData = computed(() => formattedValue.value === null && ! props.loading && ! props.error);

const deltaTone = computed(() => {
    if (props.deltaPercent === null || props.deltaPercent === undefined) {
        return 'neutral';
    }
    const positiveIsGood = ! props.inversePolarity;
    const isPositive = props.deltaPercent > 0;
    if (props.deltaPercent === 0) {
        return 'neutral';
    }
    if (isPositive) {
        return positiveIsGood ? 'good' : 'bad';
    }
    return positiveIsGood ? 'bad' : 'good';
});

const deltaIcon = computed(() => {
    if (props.deltaPercent === null || props.deltaPercent === 0) {
        return '';
    }
    return props.deltaPercent > 0 ? '↑' : '↓';
});

const deltaText = computed(() => {
    if (props.deltaPercent === null) {
        return t('executive_dashboard.trend.no_comparison');
    }
    const abs = Math.abs(props.deltaPercent);
    return `${abs.toFixed(1)}%`;
});

const trendSummary = computed(() => {
    if (props.deltaPercent === null) {
        return t('executive_dashboard.trend.no_comparison');
    }
    if (props.deltaPercent === 0) {
        return t('executive_dashboard.trend.flat');
    }
    return props.deltaPercent > 0
        ? t('executive_dashboard.trend.up')
        : t('executive_dashboard.trend.down');
});

const ariaLabel = computed(() => {
    if (props.loading) {
        return `${props.label}: carregando`;
    }
    if (props.error) {
        return `${props.label}: não foi possível carregar`;
    }
    if (hasNoData.value) {
        return `${props.label}: ${t('executive_dashboard.kpi.no_data')}`;
    }
    const trendText = props.deltaPercent === null
        ? trendSummary.value
        : `${trendSummary.value}, ${t('executive_dashboard.trend.change_vs_previous', { percent: deltaText.value })}`;
    return `${props.label}: ${formattedValue.value}, ${trendText}`;
});

const toneClasses = {
    good: { text: 'text-success-600', bg: 'bg-success-50' },
    bad: { text: 'text-danger-600', bg: 'bg-danger-50' },
    neutral: { text: 'text-foreground-muted', bg: 'bg-surface-muted' },
};
</script>

<template>
    <article
        :aria-label="ariaLabel"
        class="p-4 rounded-xl border border-border bg-surface-elevated transition-all hover:shadow-card"
    >
        <!-- Loading skeleton -->
        <template v-if="loading && value === null">
            <div class="h-3 w-32 rounded bg-surface-muted animate-pulse mb-3"></div>
            <div class="h-9 w-20 rounded bg-surface-muted animate-pulse mb-2"></div>
            <div class="h-4 w-24 rounded bg-surface-muted animate-pulse"></div>
        </template>

        <!-- Error state -->
        <template v-else-if="error">
            <p class="text-sm font-medium text-foreground-muted mb-2">{{ label }}</p>
            <p class="text-sm text-danger-600">Não foi possível carregar.</p>
        </template>

        <!-- Empty (no data) -->
        <template v-else-if="hasNoData">
            <p class="text-sm font-medium text-foreground-muted mb-2">{{ label }}</p>
            <p class="text-base text-foreground-subtle italic">{{ t('executive_dashboard.kpi.no_data') }}</p>
        </template>

        <!-- Normal: valor + delta + sparkline (opcional) -->
        <template v-else>
            <header class="flex items-start justify-between gap-2 mb-3">
                <p class="text-xs font-medium text-foreground-muted uppercase tracking-wide">{{ label }}</p>
            </header>
            <div class="flex items-end justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-2xl font-semibold text-foreground tabular-nums truncate">
                        {{ formattedValue }}
                    </p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <span
                            v-if="deltaIcon"
                            :class="[
                                'inline-flex items-center justify-center w-4 h-4 rounded text-xs font-bold',
                                toneClasses[deltaTone].text,
                                toneClasses[deltaTone].bg,
                            ]"
                            aria-hidden="true"
                        >{{ deltaIcon }}</span>
                        <span
                            :class="['text-xs font-medium', toneClasses[deltaTone].text]"
                            :title="trendSummary"
                        >
                            {{ deltaText }}
                        </span>
                    </div>
                </div>
                <div v-if="sparklinePoints && sparklinePoints.length >= 2" class="shrink-0">
                    <Sparkline :points="sparklinePoints" :width="64" :height="28" :color="deltaTone === 'good' ? 'rgb(22 163 74)' : deltaTone === 'bad' ? 'rgb(220 38 38)' : 'rgb(100 116 139)'" />
                </div>
            </div>
        </template>
    </article>
</template>

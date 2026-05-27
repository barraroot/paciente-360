<script setup>
import { computed } from 'vue';

/**
 * T259 (Fase 8 — Lote E US-10.1) — Card de KPI com tendência.
 *
 * Mostra valor + comparação % vs período anterior (Q11).
 * Cor da delta: verde positivo / vermelho negativo / cinza neutro.
 */
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    unit: { type: String, default: '' },
    deltaPercent: { type: [Number, null], default: null },
    drillable: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['drill']);

const deltaColor = computed(() => {
    if (props.deltaPercent === null) return 'text-foreground-muted';
    if (props.deltaPercent > 0) return 'text-success-600';
    if (props.deltaPercent < 0) return 'text-danger-600';
    return 'text-foreground-muted';
});

const deltaIcon = computed(() => {
    if (props.deltaPercent === null) return '';
    if (props.deltaPercent > 0) return '▲';
    if (props.deltaPercent < 0) return '▼';
    return '■';
});

const formattedDelta = computed(() => {
    if (props.deltaPercent === null) return '—';
    return `${props.deltaPercent > 0 ? '+' : ''}${props.deltaPercent.toFixed(1)}%`;
});

function onClick() {
    if (props.drillable && !props.loading) emit('drill');
}
</script>

<template>
    <article
        class="kpi-card"
        :class="{ 'kpi-card--drillable': drillable }"
        :role="drillable ? 'button' : undefined"
        :tabindex="drillable ? 0 : undefined"
        :aria-label="`${label}: ${value}${unit}, variação ${formattedDelta}`"
        @click="onClick"
        @keydown.enter="onClick"
        @keydown.space.prevent="onClick"
    >
        <header class="kpi-card__label">{{ label }}</header>
        <div v-if="loading" class="kpi-card__skeleton" aria-busy="true" />
        <div v-else class="kpi-card__value">
            {{ value }}<span v-if="unit" class="kpi-card__unit">{{ unit }}</span>
        </div>
        <footer class="kpi-card__delta" :class="deltaColor">
            <span aria-hidden="true">{{ deltaIcon }}</span>
            <span>{{ formattedDelta }}</span>
            <span class="kpi-card__delta-context">vs período anterior</span>
        </footer>
    </article>
</template>

<style scoped>
.kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.25rem;
    transition:
        box-shadow 0.15s,
        transform 0.15s;
}
.kpi-card--drillable {
    cursor: pointer;
}
.kpi-card--drillable:hover,
.kpi-card--drillable:focus-visible {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
    outline: 2px solid #2563eb;
}
.kpi-card__label {
    color: #64748b;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}
.kpi-card__value {
    font-size: 1.875rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
}
.kpi-card__unit {
    font-size: 1rem;
    color: #64748b;
    margin-left: 0.25rem;
}
.kpi-card__skeleton {
    height: 1.875rem;
    background: linear-gradient(90deg, #f1f5f9, #e2e8f0, #f1f5f9);
    border-radius: 0.375rem;
    animation: shimmer 1.6s infinite;
}
.kpi-card__delta {
    margin-top: 0.75rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.kpi-card__delta-context {
    color: #94a3b8;
    font-size: 0.75rem;
    margin-left: 0.25rem;
}
@keyframes shimmer {
    0% {
        opacity: 0.5;
    }
    50% {
        opacity: 1;
    }
    100% {
        opacity: 0.5;
    }
}
</style>

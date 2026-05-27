<script setup>
/**
 * Badge/pill de status (feature 016 · T028 / G8).
 *
 * Par tonal semântico via tokens (fundo *-50/100, texto *-700/800).
 */
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral', // neutral | success | warning | danger | info | primary
    },
});

const VARIANTS = {
    neutral: 'bg-surface-muted text-foreground-muted border-border',
    success: 'bg-success-50 text-success-800 border-success-200',
    warning: 'bg-warning-50 text-warning-800 border-warning-200',
    danger: 'bg-danger-50 text-danger-700 border-danger-200',
    info: 'bg-info-50 text-info-500 border-info-50',
    primary: 'bg-primary-50 text-primary-700 border-primary-200',
};

const classes = computed(() => [
    'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium',
    VARIANTS[props.variant] ?? VARIANTS.neutral,
]);
</script>

<template>
    <span :class="classes"><slot /></span>
</template>

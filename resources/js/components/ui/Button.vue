<script setup>
/**
 * Botão padrão (feature 016 · T028 / G8).
 *
 * Variantes e estados extraídos do `component-standard.md` (telas de referência
 * Pacientes/Agenda). Usa SEMPRE tokens — nunca cor crua.
 */
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary', // primary | secondary | danger | ghost
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const VARIANTS = {
    primary: 'bg-primary-700 text-white hover:bg-primary-800 focus-visible:outline-primary-500',
    secondary:
        'border border-border text-foreground hover:bg-surface-muted focus-visible:outline-primary-500',
    danger: 'border border-danger-500 text-danger-600 hover:bg-danger-50 focus-visible:outline-danger-500',
    ghost: 'text-primary-600 hover:bg-primary-50 focus-visible:outline-primary-500',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition',
    'focus-visible:outline focus-visible:outline-2',
    'disabled:cursor-not-allowed disabled:opacity-60',
    VARIANTS[props.variant] ?? VARIANTS.primary,
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <button :type="type" :class="classes" :disabled="disabled || loading">
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
        <slot />
    </button>
</template>

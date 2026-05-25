<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Badge de status de canal com ícone SVG inline e texto i18n.
 *
 * Props:
 *   status  — 'ativo' | 'desconectado' | 'invalido' | 'expirado' | 'degradado' | 'suspenso'
 *   showIcon — exibe o ícone à esquerda (default: true)
 */
const props = defineProps({
    status: {
        type: String,
        required: true,
        validator: (v) =>
            ['ativo', 'conectando', 'desconectado', 'invalido', 'expirado', 'degradado', 'suspenso'].includes(v),
    },
    showIcon: {
        type: Boolean,
        default: true,
    },
});

const { t } = useI18n();

const CONFIG = {
    ativo: {
        classes: 'bg-success-50 text-success-700 border-success-200',
        icon: 'check-circle',
    },
    conectando: {
        classes: 'bg-primary-50 text-primary-700 border-primary-200',
        icon: 'clock',
    },
    desconectado: {
        classes: 'bg-surface text-foreground-muted border-border',
        icon: 'x-circle',
    },
    invalido: {
        classes: 'bg-danger-50 text-danger-700 border-danger-200',
        icon: 'x-circle',
    },
    expirado: {
        classes: 'bg-warning-50 text-warning-700 border-warning-200',
        icon: 'clock',
    },
    degradado: {
        classes: 'bg-warning-50 text-warning-700 border-warning-200',
        icon: 'exclamation-triangle',
    },
    suspenso: {
        classes: 'bg-danger-50 text-danger-700 border-danger-200',
        icon: 'exclamation-triangle',
    },
};

const config = computed(() => CONFIG[props.status] ?? CONFIG.desconectado);
const label = computed(() => t(`canais.status.${props.status}`));
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
        :class="config.classes"
        :aria-label="label"
    >
        <!-- check-circle -->
        <svg
            v-if="showIcon && config.icon === 'check-circle'"
            aria-hidden="true"
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
        >
            <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
                clip-rule="evenodd"
            />
        </svg>

        <!-- x-circle -->
        <svg
            v-else-if="showIcon && config.icon === 'x-circle'"
            aria-hidden="true"
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
        >
            <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z"
                clip-rule="evenodd"
            />
        </svg>

        <!-- clock -->
        <svg
            v-else-if="showIcon && config.icon === 'clock'"
            aria-hidden="true"
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
        >
            <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z"
                clip-rule="evenodd"
            />
        </svg>

        <!-- exclamation-triangle -->
        <svg
            v-else-if="showIcon && config.icon === 'exclamation-triangle'"
            aria-hidden="true"
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
        >
            <path
                fill-rule="evenodd"
                d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
                clip-rule="evenodd"
            />
        </svg>

        {{ label }}
    </span>
</template>

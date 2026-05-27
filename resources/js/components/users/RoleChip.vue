<script setup>
const props = defineProps({
    role: {
        type: String,
        required: true,
    },
});

const ROLE_STYLES = {
    'admin-clinica': 'bg-primary-100 text-primary-800',
    medico: 'bg-success-50 text-success-700',
    atendente: 'bg-warning-50 text-warning-700',
    recepcionista: 'bg-purple-100 text-purple-800',
    financeiro: 'bg-primary-100 text-primary-800',
};

const ROLE_LABELS = {
    'admin-clinica': 'Admin Clínica',
    medico: 'Médico',
    atendente: 'Atendente',
    recepcionista: 'Recepcionista',
    financeiro: 'Financeiro',
};

const colorClass = ROLE_STYLES[props.role] ?? 'bg-surface text-foreground-muted';
const label = ROLE_LABELS[props.role] ?? props.role;
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="colorClass"
    >
        {{ label }}
    </span>
</template>

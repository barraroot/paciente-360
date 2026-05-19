<script setup>
/**
 * T078b — Pill de status de receita (US-8.1).
 *
 * Status:
 *  - active     → verde
 *  - cancelled  → cinza
 *  - superseded → cinza-escuro (substituída por renovação)
 *
 * A11y: role=img + aria-label.
 */
const props = defineProps({
  status: {
    type: String,
    required: true,
    validator: (v) => ['active', 'cancelled', 'superseded'].includes(v),
  },
})

const CONFIG = {
  active: {
    label: 'Ativa',
    ariaLabel: 'Receita ativa',
    classes: 'bg-success-100 text-success-800 ring-1 ring-success-200',
  },
  cancelled: {
    label: 'Cancelada',
    ariaLabel: 'Receita cancelada',
    classes: 'bg-surface-elevated text-foreground-muted ring-1 ring-border',
  },
  superseded: {
    label: 'Substituída',
    ariaLabel: 'Receita substituída por renovação',
    classes: 'bg-neutral-200 text-neutral-700 ring-1 ring-neutral-300',
  },
}

const config = CONFIG[props.status] ?? CONFIG.cancelled
</script>

<template>
  <span
    role="img"
    :aria-label="config.ariaLabel"
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
    :class="config.classes"
  >
    {{ config.label }}
  </span>
</template>

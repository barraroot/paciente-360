<script setup>
/**
 * T078a — Badge de tipo de receita (US-8.1).
 *
 * Tipos:
 *  - common     → azul  (Receita Comum)
 *  - special    → amarelo (Receita Especial — Portaria 344/98, listas B1/B2)
 *  - controlled → vermelho (Receita Controlada — Portaria 344/98, listas A)
 *
 * A11y: role=img + aria-label descritivo.
 */
const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (v) => ['common', 'special', 'controlled'].includes(v),
  },
})

const CONFIG = {
  common: {
    label: 'Comum',
    ariaLabel: 'Receita Comum',
    tooltip: 'Receita Comum',
    classes: 'bg-primary-100 text-primary-800 ring-1 ring-primary-200',
  },
  special: {
    label: 'Especial',
    ariaLabel: 'Receita Especial (Portaria 344/98, listas B1/B2)',
    tooltip: 'Receita Especial (Portaria 344/98)',
    classes: 'bg-warning-100 text-warning-800 ring-1 ring-warning-200',
  },
  controlled: {
    label: 'Controlada',
    ariaLabel: 'Receita Controlada (Portaria 344/98)',
    tooltip: 'Receita Controlada (Portaria 344/98)',
    classes: 'bg-danger-100 text-danger-800 ring-1 ring-danger-200',
  },
}

const config = CONFIG[props.type] ?? CONFIG.common
</script>

<template>
  <span
    role="img"
    :aria-label="config.ariaLabel"
    :title="config.tooltip"
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
    :class="config.classes"
  >
    {{ config.label }}
  </span>
</template>

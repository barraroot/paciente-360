<script setup>
/**
 * T078c — Banner informativo de receita controlada mascarada (US-8.1 / Q8).
 *
 * Exibido quando o payload retorna `masked=true` (usuário sem
 * `prescription.view_controlled`). Informa restrição de acesso.
 *
 * A11y: role=status (live region assertiva não — é informação persistente, não transitória).
 */
defineProps({
  masked: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <div
    v-if="masked"
    role="status"
    class="flex items-start gap-3 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800"
  >
    <!-- Ícone shield -->
    <svg
      class="mt-0.5 h-4 w-4 shrink-0 text-warning-600"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.955 11.955 0 003 12c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
      />
    </svg>
    <span>
      Receita controlada — acesso restrito ao emissor e Admin Clínica.
    </span>
  </div>
</template>

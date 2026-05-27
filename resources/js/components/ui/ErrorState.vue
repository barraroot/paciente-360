<script setup>
/**
 * Estado de erro padrão (feature 016 · T028/T029 · G9).
 *
 * Bloco acionável (sem PII clínica). Emite `retry` quando o slot de ação
 * padrão é usado.
 */
defineProps({
    message: { type: String, default: 'Não foi possível carregar os dados.' },
    retryable: { type: Boolean, default: false },
});
defineEmits(['retry']);
</script>

<template>
    <div
        class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
        role="alert"
    >
        <p>{{ message }}</p>
        <button
            v-if="retryable"
            type="button"
            class="mt-2 rounded-lg border border-danger-500 px-3 py-1.5 text-xs font-medium text-danger-600 transition hover:bg-danger-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500"
            @click="$emit('retry')"
        >
            Tentar novamente
        </button>
    </div>
</template>

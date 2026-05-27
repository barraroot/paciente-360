<script setup>
/**
 * Estado de carregamento padrão (feature 016 · T028/T029 · G9).
 *
 * Skeleton de N linhas reproduzindo a densidade do conteúdo. `aria-busy`
 * para leitores de tela. Nunca tela em branco nem spinner solto sem contexto.
 */
defineProps({
    rows: { type: Number, default: 3 },
    label: { type: String, default: 'Carregando…' },
});
</script>

<template>
    <div class="space-y-3" aria-busy="true" :aria-label="label">
        <div
            v-for="n in rows"
            :key="n"
            class="h-12 animate-pulse rounded-lg bg-surface-muted"
        ></div>
    </div>
</template>

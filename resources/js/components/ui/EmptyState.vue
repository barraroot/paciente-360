<script setup>
/**
 * Estado vazio padrão (feature 016 · T028/T029 · G9).
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-xl border border-dashed border-border p-8 text-center">
        <div v-if="$slots.icon" class="mb-3 flex justify-center text-foreground-subtle">
            <slot name="icon" />
        </div>
        <p class="text-sm font-medium text-foreground">{{ title }}</p>
        <p v-if="description" class="mt-1 text-sm text-foreground-muted">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4 flex justify-center">
            <slot name="action" />
        </div>
    </div>
</template>

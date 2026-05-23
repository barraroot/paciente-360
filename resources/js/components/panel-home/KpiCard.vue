<script setup>
import HeroIcon from '@/components/layout/icons/HeroIcon.vue';

defineProps({
    icon: { type: String, required: true },
    label: { type: String, required: true },
    total: { type: [Number, String], default: 0 },
    subInfo: { type: String, default: '' },
    link: { type: [String, Object], required: true },
    ariaLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});
</script>

<template>
    <router-link
        v-if="!loading && !error"
        :to="link"
        :aria-label="ariaLabel || `${label}, ${total}`"
        class="block p-5 rounded-xl border border-border bg-surface-elevated hover:border-primary-300 hover:shadow-card transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
    >
        <div class="flex items-start justify-between mb-2">
            <span class="inline-flex w-9 h-9 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                <HeroIcon :name="icon" class="w-5 h-5" />
            </span>
        </div>
        <p class="text-3xl font-semibold text-foreground tabular-nums">{{ total }}</p>
        <p class="mt-1 text-sm font-medium text-foreground">{{ label }}</p>
        <p v-if="subInfo" class="mt-0.5 text-xs text-foreground-muted">{{ subInfo }}</p>
    </router-link>

    <!-- Loading skeleton -->
    <div
        v-else-if="loading"
        aria-hidden="true"
        class="p-5 rounded-xl border border-border bg-surface-elevated"
    >
        <div class="w-9 h-9 rounded-lg bg-surface-muted animate-pulse mb-3"></div>
        <div class="h-8 w-16 rounded bg-surface-muted animate-pulse mb-2"></div>
        <div class="h-4 w-24 rounded bg-surface-muted animate-pulse mb-1"></div>
        <div class="h-3 w-32 rounded bg-surface-muted animate-pulse"></div>
    </div>

    <!-- Error state -->
    <div
        v-else
        role="alert"
        class="p-5 rounded-xl border border-danger-200 bg-danger-50 text-sm text-danger-600"
    >
        Não foi possível carregar este card.
    </div>
</template>

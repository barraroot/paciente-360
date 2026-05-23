<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});

defineEmits(['drill']);

const { t } = useI18n();

const hasItems = computed(() => Array.isArray(props.items) && props.items.length > 0);
const isEmpty = computed(() => ! props.loading && ! props.error && ! hasItems.value);
</script>

<template>
    <section class="rounded-xl border border-border bg-surface-elevated p-5">
        <h2 class="text-base font-semibold text-foreground mb-4">
            {{ t('executive_dashboard.sections.top_procedures') }}
        </h2>

        <ul v-if="loading" role="list" aria-hidden="true" class="space-y-2">
            <li v-for="n in 5" :key="n" class="h-9 rounded-lg bg-surface-muted animate-pulse"></li>
        </ul>

        <div v-else-if="error" role="alert" class="text-sm text-danger-600 py-4">
            {{ t('executive_dashboard.errors.section') }}
        </div>

        <div v-else-if="isEmpty" class="py-6 text-center text-sm text-foreground-muted">
            {{ t('executive_dashboard.errors.section') }}
        </div>

        <ul v-else role="list" class="space-y-2">
            <li v-for="item in items" :key="item.name">
                <button
                    type="button"
                    class="w-full text-left p-2.5 rounded-lg hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition-colors"
                    @click="$emit('drill', item)"
                >
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-sm font-medium text-foreground truncate">{{ item.name }}</span>
                        <span class="text-xs font-semibold text-foreground tabular-nums shrink-0">
                            {{ t('executive_dashboard.sections.count_label', { count: item.count }) }}
                        </span>
                    </div>
                    <div class="relative h-1.5 rounded-full bg-surface-muted overflow-hidden">
                        <div
                            class="absolute inset-y-0 left-0 bg-primary-600 rounded-full"
                            :style="{ width: Math.min(100, Number(item.percentage) || 0) + '%' }"
                        ></div>
                    </div>
                    <p class="text-xs text-foreground-muted mt-1">
                        {{ t('executive_dashboard.sections.percentage_label', { percentage: (Number(item.percentage) || 0).toFixed(1) }) }}
                    </p>
                </button>
            </li>
        </ul>
    </section>
</template>

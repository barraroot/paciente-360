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

const sorted = computed(() => {
    if (! Array.isArray(props.items)) {
        return [];
    }
    return [...props.items].sort(
        (a, b) => (Number(b.occupancy_percent) || 0) - (Number(a.occupancy_percent) || 0),
    );
});
const hasItems = computed(() => sorted.value.length > 0);
const isEmpty = computed(() => ! props.loading && ! props.error && ! hasItems.value);

function isHighLoad(item) {
    return Number(item.occupancy_percent) >= 90;
}
</script>

<template>
    <section class="rounded-xl border border-border bg-surface-elevated p-5">
        <h2 class="text-base font-semibold text-foreground mb-4">
            {{ t('executive_dashboard.sections.occupancy_by_professional') }}
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
            <li
                v-for="item in sorted"
                :key="item.professional_id"
            >
                <button
                    type="button"
                    class="w-full text-left p-2.5 rounded-lg hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition-colors"
                    @click="$emit('drill', item)"
                >
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="flex-1 min-w-0 flex items-center gap-2">
                            <span class="text-sm font-medium text-foreground truncate">{{ item.name }}</span>
                            <span
                                v-if="isHighLoad(item)"
                                class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-danger-50 text-danger-700"
                                :aria-label="t('executive_dashboard.aria.occupancy_high', { name: item.name, percent: (Number(item.occupancy_percent) || 0).toFixed(0) })"
                            >
                                ⚠ {{ t('executive_dashboard.sections.high_load') }}
                            </span>
                        </span>
                        <span class="text-xs font-semibold text-foreground tabular-nums shrink-0">
                            {{ (Number(item.occupancy_percent) || 0).toFixed(0) }}%
                        </span>
                    </div>
                    <div
                        class="relative h-1.5 rounded-full bg-surface-muted overflow-hidden"
                        role="progressbar"
                        :aria-valuenow="Math.round(Number(item.occupancy_percent) || 0)"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="absolute inset-y-0 left-0 rounded-full"
                            :class="isHighLoad(item) ? 'bg-danger-500' : 'bg-primary-600'"
                            :style="{ width: Math.min(100, Number(item.occupancy_percent) || 0) + '%' }"
                        ></div>
                    </div>
                </button>
            </li>
        </ul>
    </section>
</template>

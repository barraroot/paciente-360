<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    section: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const { t } = useI18n();

const items = computed(() => props.section?.data ?? []);
const hasError = computed(() => Boolean(props.section?.error));
const isEmpty = computed(() => ! props.loading && ! hasError && items.value.length === 0);

const severityClasses = {
    danger: { bg: 'bg-danger-50', text: 'text-danger-600', dot: 'bg-danger-500' },
    warn: { bg: 'bg-warning-50', text: 'text-warning-600', dot: 'bg-warning-500' },
    info: { bg: 'bg-primary-50', text: 'text-primary-700', dot: 'bg-primary-500' },
};
</script>

<template>
    <section class="rounded-xl border border-border bg-surface-elevated p-5">
        <h2 class="text-base font-semibold text-foreground mb-4">
            {{ t('panel_home.attention.heading') }}
        </h2>

        <ul v-if="loading" role="list" aria-hidden="true" class="space-y-2">
            <li v-for="n in 3" :key="n" class="h-14 rounded-lg bg-surface-muted animate-pulse"></li>
        </ul>

        <div
            v-else-if="hasError"
            role="alert"
            class="text-sm text-danger-600 py-4"
        >
            {{ t('panel_home.errors.section') }}
        </div>

        <div
            v-else-if="isEmpty"
            class="py-6 text-center text-sm text-foreground-muted"
        >
            <p class="mb-1">✅</p>
            <p>{{ t('panel_home.attention.empty') }}</p>
        </div>

        <ul v-else role="list" class="space-y-1">
            <li v-for="(item, idx) in items" :key="idx">
                <router-link
                    :to="item.link"
                    class="flex items-start gap-3 p-2.5 rounded-lg hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition-colors"
                >
                    <span
                        :class="['shrink-0 inline-flex w-2 h-2 mt-2 rounded-full', severityClasses[item.severity]?.dot ?? 'bg-primary-500']"
                        aria-hidden="true"
                    ></span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-medium text-foreground">{{ item.title }}</span>
                        <span class="block text-xs text-foreground-muted">{{ item.description }}</span>
                    </span>
                    <span
                        :class="['shrink-0 sr-only', severityClasses[item.severity]?.text ?? 'text-primary-700']"
                    >
                        {{ t(`panel_home.attention.severity_${item.severity}`) }}
                    </span>
                </router-link>
            </li>
        </ul>
    </section>
</template>

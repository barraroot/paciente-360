<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { DateTime } from 'luxon';

const props = defineProps({
    section: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const { t } = useI18n();

const items = computed(() => props.section?.data ?? []);
const hasError = computed(() => Boolean(props.section?.error));
const isEmpty = computed(() => ! props.loading && ! hasError && items.value.length === 0);

function relativeTime(iso) {
    if (! iso) {
        return '';
    }
    return DateTime.fromISO(iso).setLocale('pt-BR').toRelative() ?? '';
}
</script>

<template>
    <section class="rounded-xl border border-border bg-surface-elevated p-5">
        <h2 class="text-base font-semibold text-foreground mb-4">
            {{ t('panel_home.activity.heading') }}
        </h2>

        <ul v-if="loading" role="list" aria-hidden="true" class="space-y-2">
            <li v-for="n in 4" :key="n" class="h-12 rounded-lg bg-surface-muted animate-pulse"></li>
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
            {{ t('panel_home.activity.empty') }}
        </div>

        <ol v-else role="list" class="space-y-2">
            <li
                v-for="entry in items"
                :key="entry.id"
                class="flex items-start gap-3"
            >
                <span
                    class="shrink-0 inline-flex w-8 h-8 items-center justify-center rounded-full bg-primary-100 text-primary-700 text-xs font-semibold"
                    aria-hidden="true"
                >
                    {{ entry.actor.initials }}
                </span>
                <span class="flex-1 min-w-0 pt-0.5">
                    <component
                        :is="entry.link ? 'router-link' : 'span'"
                        :to="entry.link"
                        :class="entry.link
                            ? 'block text-sm text-foreground hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded'
                            : 'block text-sm text-foreground'"
                    >
                        {{ entry.description }}
                    </component>
                    <span class="block text-xs text-foreground-muted mt-0.5">{{ relativeTime(entry.occurred_at) }}</span>
                </span>
            </li>
        </ol>
    </section>
</template>

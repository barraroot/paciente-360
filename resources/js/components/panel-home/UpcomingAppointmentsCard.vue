<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import HeroIcon from '@/components/layout/icons/HeroIcon.vue';

const props = defineProps({
    section: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const { t } = useI18n();

const items = computed(() => props.section?.data ?? []);
const hasError = computed(() => Boolean(props.section?.error));
const isEmpty = computed(() => ! props.loading && ! hasError && items.value.length === 0);
</script>

<template>
    <section class="rounded-xl border border-border bg-surface-elevated p-5">
        <header class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-foreground">
                {{ t('panel_home.upcoming.heading') }}
            </h2>
            <router-link
                to="/panel/agenda"
                class="text-xs font-medium text-primary-700 hover:text-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
            >
                {{ t('panel_home.upcoming.see_all') }} →
            </router-link>
        </header>

        <!-- Loading skeleton -->
        <ul v-if="loading" role="list" aria-hidden="true" class="space-y-2">
            <li v-for="n in 3" :key="n" class="h-14 rounded-lg bg-surface-muted animate-pulse"></li>
        </ul>

        <!-- Error -->
        <div
            v-else-if="hasError"
            role="alert"
            class="text-sm text-danger-600 py-4"
        >
            {{ t('panel_home.errors.section') }}
        </div>

        <!-- Empty -->
        <div
            v-else-if="isEmpty"
            class="py-6 text-center text-sm text-foreground-muted"
        >
            <p class="mb-1">🌤️</p>
            <p>{{ t('panel_home.upcoming.empty') }}</p>
        </div>

        <!-- Lista -->
        <ul v-else role="list" class="space-y-1">
            <li v-for="apt in items" :key="apt.id">
                <router-link
                    to="/panel/agenda"
                    class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition-colors"
                >
                    <span class="shrink-0 w-12 text-center">
                        <span class="block text-sm font-semibold tabular-nums text-foreground">{{ apt.starts_at_local }}</span>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-medium text-foreground truncate">{{ apt.patient_name }}</span>
                        <span class="block text-xs text-foreground-muted truncate">
                            {{ apt.appointment_type }} · {{ apt.professional_name }}
                        </span>
                    </span>
                    <span
                        class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                        :class="apt.status === 'confirmed'
                            ? 'bg-success-50 text-success-700'
                            : 'bg-warning-50 text-warning-700'"
                    >
                        {{ apt.status === 'confirmed'
                            ? t('panel_home.upcoming.status_confirmed')
                            : t('panel_home.upcoming.status_scheduled') }}
                    </span>
                </router-link>
            </li>
        </ul>
    </section>
</template>

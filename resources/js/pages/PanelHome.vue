<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth.js';
import { usePanelHome } from '@/composables/usePanelHome.js';
import KpiCardsGrid from '@/components/panel-home/KpiCardsGrid.vue';
import UpcomingAppointmentsCard from '@/components/panel-home/UpcomingAppointmentsCard.vue';
import AttentionItemsCard from '@/components/panel-home/AttentionItemsCard.vue';
import RecentActivityCard from '@/components/panel-home/RecentActivityCard.vue';
import ScopeToggle from '@/components/panel-home/ScopeToggle.vue';
import RefreshButton from '@/components/panel-home/RefreshButton.vue';

const { t } = useI18n();
const auth = useAuthStore();
const { data, loading, error, scope, setScope, refresh } = usePanelHome();

const userName = computed(() => auth.user?.name ?? '');
const canToggle = computed(() => Boolean(data.value?.can_toggle_scope));
const sections = computed(() => data.value?.sections ?? {});
</script>

<template>
    <div class="p-4 sm:p-6 max-w-7xl mx-auto">
        <!-- Header: saudação + toggle + refresh -->
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    {{ t('panel_home.greeting', { name: userName }) }}
                </h1>
                <p class="mt-0.5 text-sm text-foreground-muted">
                    {{ t('panel_home.subtitle') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <ScopeToggle
                    v-if="canToggle"
                    :model-value="scope"
                    @update:model-value="setScope"
                />
                <RefreshButton :loading="loading" @refresh="refresh" />
            </div>
        </header>

        <!-- Banner de erro global -->
        <div
            v-if="error"
            role="alert"
            class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
        >
            <span>{{ t('panel_home.errors.global') }}</span>
            <button
                type="button"
                class="font-medium underline hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 rounded"
                @click="refresh"
            >
                {{ t('panel_home.errors.retry') }}
            </button>
        </div>

        <!-- KPIs -->
        <div class="mb-6">
            <KpiCardsGrid :section="sections.kpis" :loading="loading && !data" />
        </div>

        <!-- Duas colunas: upcoming + attention -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <UpcomingAppointmentsCard :section="sections.upcoming_appointments" :loading="loading && !data" />
            <AttentionItemsCard :section="sections.attention_items" :loading="loading && !data" />
        </div>

        <!-- Activity full width -->
        <RecentActivityCard :section="sections.recent_activity" :loading="loading && !data" />
    </div>
</template>

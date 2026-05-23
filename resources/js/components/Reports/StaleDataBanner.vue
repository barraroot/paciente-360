<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { DateTime } from 'luxon';

const props = defineProps({
    lagSeconds: { type: [Number, null], default: null },
    window: { type: String, default: '7d' },
});

const { t } = useI18n();

const isVisible = computed(() => {
    if (props.window === '24h') {
        // FR-008: oculto na janela 24h (dados live por definição).
        return false;
    }
    const lag = Number(props.lagSeconds);
    return Number.isFinite(lag) && lag > 7200; // > 2h
});

const relative = computed(() => {
    const lag = Number(props.lagSeconds);
    if (! Number.isFinite(lag)) {
        return '';
    }
    return DateTime.now().minus({ seconds: lag }).setLocale('pt-BR').toRelative() ?? '';
});
</script>

<template>
    <div
        v-if="isVisible"
        role="status"
        aria-live="polite"
        class="mb-4 flex items-start gap-3 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700"
    >
        <span aria-hidden="true" class="mt-0.5">⏱</span>
        <div class="flex-1">
            <p class="font-medium">{{ t('executive_dashboard.stale_banner.title') }}</p>
            <p class="text-xs text-warning-600 mt-0.5">
                {{ t('executive_dashboard.stale_banner.updated_ago', { relative }) }}
            </p>
        </div>
    </div>
</template>

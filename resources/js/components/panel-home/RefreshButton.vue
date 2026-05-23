<script setup>
import { useI18n } from 'vue-i18n';
import HeroIcon from '@/components/layout/icons/HeroIcon.vue';

defineProps({
    loading: { type: Boolean, default: false },
});

defineEmits(['refresh']);

const { t } = useI18n();
</script>

<template>
    <button
        type="button"
        :disabled="loading"
        :aria-busy="loading"
        :aria-label="loading ? t('panel_home.refreshing') : t('panel_home.refresh')"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-border text-sm font-medium text-foreground hover:border-primary-300 hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
        @click="$emit('refresh')"
    >
        <HeroIcon
            name="chevron-right"
            class="w-4 h-4 transition-transform"
            :class="loading ? 'animate-spin' : ''"
        />
        {{ loading ? t('panel_home.refreshing') : t('panel_home.refresh') }}
    </button>
</template>

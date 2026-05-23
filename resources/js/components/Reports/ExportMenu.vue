<script setup>
import { ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { onClickOutside } from '@vueuse/core';
import HeroIcon from '@/components/layout/icons/HeroIcon.vue';

const props = defineProps({
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['export-pdf']);
const { t } = useI18n();

const isOpen = ref(false);
const triggerEl = useTemplateRef('triggerEl');
const menuEl = useTemplateRef('menuEl');

onClickOutside(menuEl, () => {
    isOpen.value = false;
}, { ignore: [triggerEl] });

function toggle() {
    isOpen.value = ! isOpen.value;
}

function onKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        isOpen.value = false;
        triggerEl.value?.focus();
    }
}

function handlePdf() {
    isOpen.value = false;
    emit('export-pdf');
}
</script>

<template>
    <div class="relative" @keydown="onKeydown">
        <button
            ref="triggerEl"
            type="button"
            :aria-label="t('executive_dashboard.aria.export_menu')"
            :aria-expanded="isOpen"
            aria-haspopup="menu"
            :disabled="loading"
            :aria-busy="loading"
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-border text-sm font-medium text-foreground hover:border-primary-300 hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
            @click="toggle"
        >
            <HeroIcon
                name="chevron-down"
                class="w-4 h-4 transition-transform"
                :class="loading ? 'animate-spin' : ''"
            />
            {{ loading ? t('executive_dashboard.export.exporting') : t('executive_dashboard.export.title') }}
        </button>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="menuEl"
                role="menu"
                class="fixed z-50 mt-2 w-48 rounded-lg border border-border bg-surface-elevated shadow-popover py-1"
                :style="{
                    top: (triggerEl?.getBoundingClientRect().bottom ?? 0) + 4 + 'px',
                    right: 'calc(100vw - ' + (triggerEl?.getBoundingClientRect().right ?? 0) + 'px)',
                }"
            >
                <button
                    type="button"
                    role="menuitem"
                    class="w-full text-left px-3 py-2 text-sm text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="handlePdf"
                >
                    {{ t('executive_dashboard.export.pdf') }}
                </button>
                <button
                    type="button"
                    role="menuitem"
                    disabled
                    aria-disabled="true"
                    class="w-full text-left px-3 py-2 text-sm text-foreground-subtle cursor-not-allowed flex items-center justify-between"
                    :title="t('executive_dashboard.export.csv_disabled')"
                >
                    {{ t('executive_dashboard.export.csv') }}
                    <span class="text-[10px] uppercase tracking-wide text-foreground-subtle">
                        {{ t('executive_dashboard.export.csv_disabled') }}
                    </span>
                </button>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { useTemplateRef, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '7d' },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const tablistRef = useTemplateRef('tablistRef');
const windows = ['24h', '7d', '30d', '90d'];

function select(value) {
    if (value !== props.modelValue) {
        emit('update:modelValue', value);
    }
}

async function moveFocus(currentIndex, direction) {
    const len = windows.length;
    const nextIndex = ((currentIndex + direction) % len + len) % len;
    const value = windows[nextIndex];
    emit('update:modelValue', value);
    await nextTick();
    const btn = tablistRef.value?.querySelector(`[data-window="${value}"]`);
    btn?.focus();
}

function onKeydown(event, index) {
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        moveFocus(index, 1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        moveFocus(index, -1);
    } else if (event.key === 'Home') {
        event.preventDefault();
        moveFocus(-1, 1);
    } else if (event.key === 'End') {
        event.preventDefault();
        moveFocus(0, -1);
    }
}
</script>

<template>
    <div
        ref="tablistRef"
        role="tablist"
        :aria-label="t('executive_dashboard.aria.tablist')"
        class="inline-flex rounded-lg border border-border p-0.5 bg-surface"
    >
        <button
            v-for="(value, idx) in windows"
            :key="value"
            type="button"
            role="tab"
            :aria-selected="modelValue === value"
            :tabindex="modelValue === value ? 0 : -1"
            :data-window="value"
            class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            :class="modelValue === value
                ? 'bg-primary-700 text-white'
                : 'text-foreground-muted hover:text-foreground'"
            @click="select(value)"
            @keydown="onKeydown($event, idx)"
        >
            {{ t(`executive_dashboard.period_filter.${value}`) }}
        </button>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: { type: String, default: 'user' },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

function setScope(value) {
    if (value !== props.modelValue) {
        emit('update:modelValue', value);
    }
}
</script>

<template>
    <div
        role="group"
        :aria-label="t('panel_home.scope.label')"
        class="inline-flex rounded-lg border border-border p-0.5 bg-surface"
    >
        <button
            type="button"
            :aria-pressed="modelValue === 'user'"
            class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            :class="modelValue === 'user'
                ? 'bg-primary-700 text-white'
                : 'text-foreground-muted hover:text-foreground'"
            @click="setScope('user')"
        >
            {{ t('panel_home.scope.user') }}
        </button>
        <button
            type="button"
            :aria-pressed="modelValue === 'clinic'"
            class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            :class="modelValue === 'clinic'
                ? 'bg-primary-700 text-white'
                : 'text-foreground-muted hover:text-foreground'"
            @click="setScope('clinic')"
        >
            {{ t('panel_home.scope.clinic') }}
        </button>
    </div>
</template>

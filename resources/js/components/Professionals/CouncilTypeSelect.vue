<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: { type: String, default: '' },
    otherValue: { type: String, default: '' },
    error: { type: String, default: null },
    errorOther: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue', 'update:otherValue']);
const { t } = useI18n();

const types = ['CRM', 'CRO', 'COREN', 'CRP', 'OUTRO'];

const isOther = computed(() => props.modelValue === 'OUTRO');
</script>

<template>
    <div class="space-y-2">
        <label for="prof-council-type" class="block text-sm font-medium text-foreground">
            {{ t('professionals.form.council_type_label') }} <span class="text-danger-500">*</span>
        </label>
        <select
            id="prof-council-type"
            :value="modelValue"
            @change="emit('update:modelValue', $event.target.value)"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
            :class="{ 'border-danger-500': error }"
        >
            <option value="">—</option>
            <option v-for="type in types" :key="type" :value="type">
                {{ t(`professionals.council_types.${type}`) }}
            </option>
        </select>
        <p v-if="error" class="text-xs text-danger-500">{{ error }}</p>

        <div v-if="isOther" class="space-y-1">
            <label for="prof-council-type-other" class="block text-xs font-medium text-foreground-muted">
                {{ t('professionals.form.council_type_other_label') }} <span class="text-danger-500">*</span>
            </label>
            <input
                id="prof-council-type-other"
                type="text"
                :value="otherValue"
                @input="emit('update:otherValue', $event.target.value)"
                :placeholder="t('professionals.form.council_type_other_placeholder')"
                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                :class="{ 'border-danger-500': errorOther }"
                maxlength="50"
            />
            <p v-if="errorOther" class="text-xs text-danger-500">{{ errorOther }}</p>
        </div>
    </div>
</template>

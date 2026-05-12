<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const inputRef = ref(null);
const localValue = ref(props.modelValue);

// Sincroniza prop → local quando pai atualiza de fora
watch(
    () => props.modelValue,
    (val) => {
        if (val !== localValue.value) {
            localValue.value = val;
        }
    },
);

// Debounce 350ms antes de emitir
let debounceTimer = null;
const DEBOUNCE_MS = 350;
const MIN_CHARS = 2;

function onInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const val = localValue.value;
        if (val === '' || val.length >= MIN_CHARS) {
            emit('update:modelValue', val);
        }
    }, DEBOUNCE_MS);
}

function clearSearch() {
    localValue.value = '';
    clearTimeout(debounceTimer);
    emit('update:modelValue', '');
    inputRef.value?.focus();
}
</script>

<template>
    <div class="relative flex items-center px-3 py-2.5 border-b border-border bg-surface">
        <!-- Ícone de lupa / spinner -->
        <div class="absolute left-6 top-1/2 -translate-y-1/2 text-foreground-muted" aria-hidden="true">
            <!-- Loading spinner -->
            <svg
                v-if="loading"
                class="h-4 w-4 animate-spin text-primary-600"
                viewBox="0 0 24 24"
                fill="none"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <!-- Lupa -->
            <svg
                v-else
                class="h-4 w-4"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
            </svg>
        </div>

        <!-- Input de busca -->
        <input
            ref="inputRef"
            v-model="localValue"
            type="search"
            autocomplete="off"
            :placeholder="t('inbox.search_placeholder')"
            class="w-full rounded-lg border border-border bg-surface py-2 pl-9 pr-8 text-sm text-foreground placeholder-foreground-muted outline-none transition focus:border-primary-400 focus:ring-1 focus:ring-primary-400"
            :aria-label="t('inbox.search_placeholder')"
            @input="onInput"
        />

        <!-- Botão limpar (X) -->
        <button
            v-if="localValue"
            type="button"
            class="absolute right-6 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-foreground-muted transition hover:bg-surface-elevated hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            :aria-label="'Limpar busca'"
            @click="clearSearch"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>
</template>

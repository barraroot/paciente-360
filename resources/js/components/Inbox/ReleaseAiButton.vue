<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';

const { t } = useI18n();
const store = useInboxStore();

const props = defineProps({
    conversationId: {
        type: [String, Number],
        required: true,
    },
});

const emit = defineEmits(['released']);

const loading = ref(false);

async function release() {
    loading.value = true;
    try {
        const updated = await store.releaseToAi(props.conversationId);
        emit('released', updated);
    } catch (err) {
        console.error('[ReleaseAiButton] failed', err);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <button
        :disabled="loading"
        class="release-ai-button"
        @click="release"
    >
        {{ loading ? t('inbox.ai_pause.releasing') : t('inbox.ai_pause.release') }}
    </button>
</template>

<style scoped>
.release-ai-button {
    padding: 6px 14px;
    background: #6b7280;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

.release-ai-button:hover:not(:disabled) {
    background: #4b5563;
}

.release-ai-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

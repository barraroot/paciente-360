<script setup>
import { ref, watch, computed } from 'vue';
import { useIaStore } from '@/stores/ia.js';

const props = defineProps({
    conversationId: { type: [Number, String], required: true },
});

const store = useIaStore();

const state = ref(null);
const loading = ref(false);
const busy = ref(false);
const error = ref(null);

const aiEnabled = computed(() => state.value?.ai_enabled ?? false);
const paused = computed(() => state.value?.paused ?? false);
const persona = computed(() => state.value?.assignment?.persona ?? null);
const channelType = computed(() => state.value?.channel_type ?? null);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        state.value = await store.fetchConversationAiState(props.conversationId);
    } catch (e) {
        error.value = e?.response?.data?.message ?? null;
        state.value = null;
    } finally {
        loading.value = false;
    }
}

async function pause() {
    busy.value = true;
    try {
        state.value = await store.pauseConversationAi(props.conversationId);
    } finally {
        busy.value = false;
    }
}

async function resume() {
    busy.value = true;
    error.value = null;
    try {
        state.value = await store.resumeConversationAi(props.conversationId);
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Não foi possível reativar a IA.';
    } finally {
        busy.value = false;
    }
}

watch(() => props.conversationId, load, { immediate: true });
</script>

<template>
    <!-- Faixa compacta de status da IA Matricial (US6). Some quando a IA não atende o canal. -->
    <div
        v-if="!loading && aiEnabled"
        class="flex items-center justify-between gap-3 border-b border-border bg-surface-elevated px-4 py-2 text-xs"
        role="status"
    >
        <div class="flex items-center gap-2 min-w-0">
            <span
                class="inline-flex h-2 w-2 shrink-0 rounded-full"
                :class="paused ? 'bg-amber-400' : 'bg-green-500'"
                aria-hidden="true"
            ></span>
            <span class="font-medium text-foreground">
                {{ paused ? 'IA pausada' : 'IA ativa' }}
            </span>
            <span v-if="persona" class="truncate text-foreground-muted">
                · {{ persona.name }}
            </span>
            <span v-if="channelType" class="text-foreground-subtle">· {{ channelType }}</span>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button
                v-if="!paused"
                type="button"
                :disabled="busy"
                class="rounded-md border border-border px-2.5 py-1 font-medium text-foreground transition hover:bg-surface disabled:opacity-50"
                @click="pause"
            >
                Pausar IA
            </button>
            <button
                v-else
                type="button"
                :disabled="busy"
                class="rounded-md bg-primary-600 px-2.5 py-1 font-medium text-white transition hover:bg-primary-700 disabled:opacity-50"
                @click="resume"
            >
                Reativar IA
            </button>
        </div>
    </div>
    <p v-if="error" class="border-b border-border bg-red-50 px-4 py-1.5 text-xs text-red-700">{{ error }}</p>
</template>

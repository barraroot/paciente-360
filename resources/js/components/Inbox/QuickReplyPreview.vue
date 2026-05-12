<script setup>
/**
 * T243 — QuickReplyPreview
 *
 * Mostra preview do conteúdo de uma resposta rápida selecionada antes de enviar.
 * Exibe o texto renderizado (com variáveis substituídas quando disponíveis)
 * ou o template original como fallback.
 *
 * Emits:
 *   confirm  — usuário confirma o envio
 *   discard  — usuário descartou a resposta rápida
 */

import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';

const { t } = useI18n();
const store = useInboxStore();

const props = defineProps({
    /** Resposta rápida selecionada (objeto completo) */
    reply: {
        type: Object,
        required: true,
    },
    /** ID da conversa atual (para renderização server-side de variáveis) */
    conversationId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(['confirm', 'discard']);

// ─── Renderização com variáveis ───────────────────────────────────────────────

const renderedContent = ref(null);
const renderLoading = ref(false);
const renderError = ref(false);

watch(
    () => props.reply?.id,
    async (replyId) => {
        if (!replyId || !props.conversationId) {
            renderedContent.value = null;
            return;
        }

        renderLoading.value = true;
        renderError.value = false;

        try {
            const result = await store.renderQuickReply(replyId, props.conversationId);
            renderedContent.value = result?.rendered ?? null;
        } catch {
            renderError.value = true;
            renderedContent.value = null;
        } finally {
            renderLoading.value = false;
        }
    },
    { immediate: true },
);

const displayContent = () => renderedContent.value ?? props.reply?.content ?? '';
</script>

<template>
    <div
        class="border-t border-border bg-surface-elevated px-4 py-3"
        role="region"
        :aria-label="t('inbox.respostas_rapidas.preview_label')"
    >
        <!-- Header do preview -->
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="rounded bg-primary-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-primary-700">
                    {{ reply.shortcut }}
                </span>
                <span class="text-xs text-foreground-muted">
                    {{ t('inbox.respostas_rapidas.preview_label') }}
                </span>
            </div>
            <button
                type="button"
                class="rounded p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                :aria-label="t('inbox.respostas_rapidas.descartar')"
                @click="emit('discard')"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>

        <!-- Conteúdo renderizado -->
        <div class="rounded-lg border border-border bg-surface px-3 py-2">
            <div v-if="renderLoading" class="flex items-center gap-2 text-xs text-foreground-muted">
                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ t('common.carregando') }}
            </div>
            <p v-else class="whitespace-pre-wrap text-sm text-foreground">
                {{ displayContent() }}
            </p>
            <p v-if="renderError" class="mt-1 text-xs text-foreground-muted italic">
                {{ t('inbox.respostas_rapidas.preview_sem_variaveis') }}
            </p>
        </div>

        <!-- Ações -->
        <div class="mt-2 flex items-center justify-end gap-2">
            <button
                type="button"
                class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-surface-raised focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="emit('discard')"
            >
                {{ t('inbox.respostas_rapidas.descartar') }}
            </button>
            <button
                type="button"
                class="rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="emit('confirm', displayContent())"
            >
                {{ t('inbox.respostas_rapidas.usar') }}
            </button>
        </div>
    </div>
</template>

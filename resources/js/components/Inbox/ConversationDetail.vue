<script setup>
import { computed, watch, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';
import { formatDate } from '@/composables/useI18nFormat.js';
import MessageBubble from '@/components/Inbox/MessageBubble.vue';
import MessageInput from '@/components/Inbox/MessageInput.vue';

const { t } = useI18n();

const props = defineProps({
    conversation: {
        type: Object,
        required: true,
    },
    inputDisabled: {
        type: Boolean,
        default: false,
    },
});

const store = useInboxStore();
const messagesEndRef = ref(null);
const messagesContainerRef = ref(null);

const messages = computed(() => store.messagesByConversationId[props.conversation.id] ?? []);

/**
 * Agrupa mensagens por data para exibir separadores de dia.
 * @returns {Array<{ date: string, label: string, messages: Object[] }>}
 */
const messageGroups = computed(() => {
    const groups = [];
    let currentDate = null;

    for (const msg of messages.value) {
        const msgDate = msg.created_at ? new Date(msg.created_at).toDateString() : null;
        if (msgDate && msgDate !== currentDate) {
            currentDate = msgDate;
            groups.push({
                date: msgDate,
                label: formatDate(new Date(msg.created_at)),
                messages: [],
            });
        }
        if (groups.length > 0) {
            groups[groups.length - 1].messages.push(msg);
        }
    }

    return groups;
});

const isLoadingMessages = computed(() => store.loadingMessages);
const hasMoreMessages = computed(
    () => store.hasMoreMessagesByConversationId[props.conversation.id] ?? false,
);

async function loadMessages(cursor = null) {
    await store.loadMessages(props.conversation.id, cursor);
    if (!cursor) {
        scrollToBottom();
    }
}

async function loadMoreMessages() {
    const cursor = store.cursorByConversationId[props.conversation.id];
    if (!cursor || isLoadingMessages.value) { return; }
    await loadMessages(cursor);
}

function scrollToBottom() {
    setTimeout(() => {
        messagesEndRef.value?.scrollIntoView({ behavior: 'smooth' });
    }, 50);
}

watch(
    () => props.conversation.id,
    (newId) => {
        if (newId) { loadMessages(); }
    },
    { immediate: true },
);

// Scroll ao receber nova mensagem
watch(
    () => messages.value.length,
    (newLen, oldLen) => {
        if (newLen > oldLen) { scrollToBottom(); }
    },
);

function onMessageSent() {
    scrollToBottom();
}

// ─── Header info ──────────────────────────────────────────────────────────────

const channelType = computed(
    () => props.conversation.channel?.type ?? props.conversation.channel_type ?? 'web',
);

const channelBadgeClass = computed(() => {
    const map = {
        whatsapp: 'bg-[#25D366] text-white',
        instagram: 'bg-gradient-to-br from-[#833ab4] via-[#fd1d1d] to-[#fcb045] text-white',
        web: 'bg-surface border border-border text-foreground-muted',
    };
    return map[channelType.value] ?? map.web;
});

const patientName = computed(() => {
    const p = props.conversation.patient;
    if (!p) { return props.conversation.external_thread_id ?? t('inbox.contato_anonimo'); }
    return p.nome_completo ?? p.name ?? t('inbox.contato_anonimo');
});

const conversationStatus = computed(() => props.conversation.status ?? 'aberta');
</script>

<template>
    <div class="flex h-full flex-col bg-surface">
        <!-- Header da conversa -->
        <div class="flex items-center justify-between border-b border-border px-4 py-3 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <!-- Avatar -->
                <div
                    class="h-9 w-9 shrink-0 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-semibold"
                    aria-hidden="true"
                >
                    <img
                        v-if="conversation.patient?.avatar_url"
                        :src="conversation.patient.avatar_url"
                        :alt="patientName"
                        class="h-full w-full rounded-full object-cover"
                    />
                    <span v-else>{{ patientName.slice(0, 2).toUpperCase() }}</span>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-semibold text-foreground">{{ patientName }}</span>
                        <!-- Canal badge -->
                        <span
                            class="inline-flex shrink-0 h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold"
                            :class="channelBadgeClass"
                            :title="t(`inbox.canal.${channelType}`)"
                            aria-hidden="true"
                        >
                            {{ channelType === 'whatsapp' ? 'W' : channelType === 'instagram' ? 'I' : '⊙' }}
                        </span>
                    </div>
                    <p class="text-xs text-foreground-muted">
                        {{ t(`inbox.status.${conversationStatus}`) }}
                    </p>
                </div>
            </div>

            <!-- Ações da conversa (resolver / reabrir) — placeholder para US5 -->
            <div class="flex items-center gap-2 shrink-0">
                <button
                    v-if="conversationStatus !== 'resolvida'"
                    type="button"
                    class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="store.resolve(conversation.id)"
                >
                    {{ t('inbox.acoes.resolver') }}
                </button>
                <button
                    v-else
                    type="button"
                    class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="store.reopen(conversation.id)"
                >
                    {{ t('inbox.acoes.reabrir') }}
                </button>
            </div>
        </div>

        <!-- Área de mensagens -->
        <div
            ref="messagesContainerRef"
            class="flex-1 overflow-y-auto py-2"
            role="log"
            aria-label="Mensagens da conversa"
            aria-live="polite"
        >
            <!-- Botão carregar mais (scroll-back) -->
            <div v-if="hasMoreMessages" class="flex justify-center py-3">
                <button
                    type="button"
                    :disabled="isLoadingMessages"
                    class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground-muted transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60"
                    @click="loadMoreMessages"
                >
                    {{ isLoadingMessages ? t('inbox.loading') : 'Carregar mensagens anteriores' }}
                </button>
            </div>

            <!-- Loading inicial -->
            <div
                v-if="isLoadingMessages && messages.length === 0"
                class="flex items-center justify-center py-12"
                aria-live="polite"
                aria-busy="true"
            >
                <svg class="h-6 w-6 animate-spin text-primary-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="sr-only">{{ t('inbox.loading') }}</span>
            </div>

            <!-- Erro -->
            <div
                v-else-if="store.messagesError && messages.length === 0"
                class="flex flex-col items-center gap-2 py-12 text-center"
                role="alert"
            >
                <p class="text-sm text-danger-700">{{ t('inbox.errors.carregar_mensagens') }}</p>
                <button
                    type="button"
                    class="text-xs text-primary-700 underline"
                    @click="loadMessages()"
                >
                    Tentar novamente
                </button>
            </div>

            <!-- Grupos de mensagens por data -->
            <template v-for="group in messageGroups" :key="group.date">
                <!-- Separador de dia -->
                <div class="flex items-center gap-3 px-4 my-3" aria-hidden="true">
                    <div class="flex-1 h-px bg-border"></div>
                    <span class="text-xs text-foreground-muted font-medium">{{ group.label }}</span>
                    <div class="flex-1 h-px bg-border"></div>
                </div>

                <!-- Mensagens do dia -->
                <MessageBubble
                    v-for="(msg, idx) in group.messages"
                    :key="msg.id"
                    :message="msg"
                    :previous-message="idx > 0 ? group.messages[idx - 1] : null"
                />
            </template>

            <!-- Âncora para scroll automático -->
            <div ref="messagesEndRef" aria-hidden="true"></div>
        </div>

        <!-- Input de mensagem -->
        <MessageInput
            :conversation="conversation"
            :disabled="inputDisabled"
            @message-sent="onMessageSent"
        />
    </div>
</template>

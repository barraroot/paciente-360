<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';
import QuickReplyAutocomplete from '@/components/Inbox/QuickReplyAutocomplete.vue';
import QuickReplyPreview from '@/components/Inbox/QuickReplyPreview.vue';

const { t } = useI18n();

const props = defineProps({
    /** @type {Object} ConversationResource completo */
    conversation: {
        type: Object,
        required: true,
    },
    /** @type {boolean} force-disable externo (ex.: polling mode) */
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['messageSent']);

const store = useInboxStore();

// ─── Respostas Rápidas (US-4.7) ───────────────────────────────────────────────

const autocompleteRef = ref(null);
const qrQuery = ref('');
const showAutocomplete = ref(false);
const selectedReply = ref(null);

/** Carrega as respostas rápidas quando o componente é montado */
onMounted(async () => {
    if (!store.quickReplies.length) {
        await store.loadQuickReplies().catch(() => {});
    }
});

/**
 * Detecta quando o usuário digita `/` no textarea e ativa o autocomplete.
 * Atualiza qrQuery com o texto após a barra.
 */
function detectQuickReplyTrigger(value) {
    const slashIdx = value.lastIndexOf('/');
    if (slashIdx !== -1 && slashIdx === value.trimStart().indexOf('/') && slashIdx === value.indexOf('/')) {
        const afterSlash = value.slice(slashIdx + 1);
        // Não exibir se houver espaço após a barra (usuário já terminou o atalho)
        if (!afterSlash.includes(' ')) {
            qrQuery.value = afterSlash;
            showAutocomplete.value = true;
            return;
        }
    }
    showAutocomplete.value = false;
    qrQuery.value = '';
}

function onSelectQuickReply(reply) {
    showAutocomplete.value = false;
    qrQuery.value = '';
    selectedReply.value = reply;
    // Limpa o campo para o preview assumir
    body.value = '';
}

function onConfirmQuickReply(renderedContent) {
    body.value = renderedContent;
    selectedReply.value = null;
    nextTick(() => {
        autoResize();
        submit();
    });
}

function onDiscardQuickReply() {
    selectedReply.value = null;
    body.value = '';
    nextTick(() => textareaRef.value?.focus());
}

function closeAutocomplete() {
    showAutocomplete.value = false;
    qrQuery.value = '';
}

// ─── Estado do input ──────────────────────────────────────────────────────────

const body = ref('');
const textareaRef = ref(null);

const CHAR_LIMIT = 4096;

const charCount = computed(() => body.value.length);
const isOverLimit = computed(() => charCount.value > CHAR_LIMIT);

// ─── Janela 24h ──────────────────────────────────────────────────────────────

const channelType = computed(() => {
    return props.conversation.channel?.type ?? props.conversation.channel_type ?? 'web';
});

/** Web não tem restrição de janela 24h */
const hasWindowRestriction = computed(() => channelType.value !== 'web');

const window24h = computed(() => props.conversation.window_24h ?? null);

const windowState = computed(() => {
    if (!hasWindowRestriction.value) { return 'open'; }
    if (!window24h.value) { return 'open'; }

    const seconds = window24h.value.seconds_remaining ?? 0;
    if (seconds <= 0) { return 'closed'; }
    if (seconds < 3600) { return 'danger'; }
    if (seconds < 4 * 3600) { return 'warning'; }
    return 'open';
});

const windowLabel = computed(() => {
    if (!hasWindowRestriction.value) { return null; }
    if (!window24h.value) { return null; }

    const seconds = window24h.value.seconds_remaining ?? 0;
    if (seconds <= 0) { return t('inbox.janela_24h.fechada'); }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor(seconds / 60);

    if (seconds >= 3600) {
        return t('inbox.janela_24h.aberta_horas', { hours });
    }
    return t('inbox.janela_24h.atencao_minutos', { minutes });
});

const windowBadgeClass = computed(() => {
    const map = {
        open: 'bg-success-50 text-success-700 border-success-200',
        warning: 'bg-warning-50 text-warning-700 border-warning-200',
        danger: 'bg-danger-50 text-danger-700 border-danger-200',
        closed: 'bg-surface text-foreground-muted border-border',
    };
    return map[windowState.value] ?? map.open;
});

const isWindowClosed = computed(() => windowState.value === 'closed');

const isDisabled = computed(() => {
    return props.disabled || isWindowClosed.value || store.sendingMessage;
});

// ─── Idempotency key ─────────────────────────────────────────────────────────

function generateIdempotencyKey() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    // Fallback para ambientes sem crypto.randomUUID
    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

// ─── Erros e feedback ─────────────────────────────────────────────────────────

const sendError = ref(null);
const sendErrorCode = ref(null);
const windowExpiredDetails = ref(null);

/** Esvazia erros ao digitar */
watch(body, () => {
    if (sendError.value) {
        sendError.value = null;
        sendErrorCode.value = null;
        windowExpiredDetails.value = null;
    }
});

// ─── Auto-resize textarea ─────────────────────────────────────────────────────

const MAX_ROWS = 6;
const LINE_HEIGHT_PX = 22;

function autoResize() {
    const el = textareaRef.value;
    if (!el) { return; }
    el.style.height = 'auto';
    const maxHeight = MAX_ROWS * LINE_HEIGHT_PX;
    el.style.height = Math.min(el.scrollHeight, maxHeight) + 'px';
}

watch(body, async () => {
    await nextTick();
    autoResize();
});

// ─── Typing indicator ─────────────────────────────────────────────────────────

let typingTimer = null;
const TYPING_THROTTLE_MS = 1000;

function onInput() {
    autoResize();
    detectQuickReplyTrigger(body.value);

    if (!props.disabled) {
        if (!typingTimer) {
            sendTyping();
        }
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            typingTimer = null;
        }, TYPING_THROTTLE_MS);
    }
}

async function sendTyping() {
    if (!props.conversation?.id || props.disabled) { return; }
    try {
        // Placeholder: T242 implementará o endpoint de typing
        // await api.post(`/inbox/conversations/${props.conversation.id}/typing`);
    } catch {
        // Silencioso
    }
}

// ─── Submit ───────────────────────────────────────────────────────────────────

async function submit() {
    const text = body.value.trim();
    if (!text || isDisabled.value || isOverLimit.value) { return; }

    const idempotencyKey = generateIdempotencyKey();
    sendError.value = null;
    sendErrorCode.value = null;
    windowExpiredDetails.value = null;

    try {
        const message = await store.sendMessage(
            props.conversation.id,
            { body: text, content_type: 'text' },
            idempotencyKey,
        );

        body.value = '';
        await nextTick();
        autoResize();
        textareaRef.value?.focus();

        emit('messageSent', message);
    } catch (err) {
        const status = err.response?.status;
        const data = err.response?.data;
        const code = data?.code ?? data?.error;

        if (status === 422 && code === 'mensagem.bloqueada_fora_janela') {
            sendErrorCode.value = 'janela_fechada';
            windowExpiredDetails.value = data?.details ?? null;
            sendError.value = t('inbox.janela_24h.bloqueada');
        } else if (status === 503) {
            sendErrorCode.value = 'circuit_open';
            sendError.value = t('inbox.errors.circuit_open');
        } else {
            sendError.value = data?.message ?? t('inbox.errors.enviar');
        }
    }
}

function onKeydown(event) {
    // Delega para o autocomplete quando estiver aberto
    if (showAutocomplete.value && autocompleteRef.value) {
        autocompleteRef.value.onKeydown(event);
        return;
    }

    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}

// ─── Attach placeholder ───────────────────────────────────────────────────────

function handleAttach() {
    // T247 implementará o upload real — feedback inline (sem alert nativo)
    sendError.value = t('inbox.mensagem.anexar_em_breve');
    sendErrorCode.value = null;
}
</script>

<template>
    <div class="border-t border-border bg-surface-elevated">
        <!-- Preview de resposta rápida selecionada -->
        <QuickReplyPreview
            v-if="selectedReply"
            :reply="selectedReply"
            :conversation-id="conversation.id"
            @confirm="onConfirmQuickReply"
            @discard="onDiscardQuickReply"
        />

        <!-- Window 24h badge (apenas quando há restrição) -->
        <div
            v-if="hasWindowRestriction && windowLabel"
            class="flex items-center gap-2 border-b border-border px-4 py-2"
        >
            <span
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                :class="windowBadgeClass"
                role="status"
                :aria-live="isWindowClosed ? 'assertive' : 'polite'"
            >
                <template v-if="isWindowClosed">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                </template>
                <template v-else>
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                </template>
                {{ windowLabel }}
            </span>
        </div>

        <!-- Área de input -->
        <div class="relative px-4 py-3">
            <!-- Autocomplete de respostas rápidas -->
            <QuickReplyAutocomplete
                v-if="showAutocomplete"
                ref="autocompleteRef"
                :query="qrQuery"
                :replies="store.quickReplies"
                @select="onSelectQuickReply"
                @close="closeAutocomplete"
            />

            <!-- Campo de texto -->
            <div
                class="flex items-end gap-2 rounded-xl border transition-colors"
                :class="[
                    isDisabled
                        ? 'border-border bg-surface opacity-70'
                        : 'border-border bg-surface focus-within:border-primary-400',
                ]"
            >
                <!-- Botão anexar -->
                <button
                    type="button"
                    :disabled="isDisabled"
                    class="ml-2 mb-2 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :aria-label="t('inbox.mensagem.anexar')"
                    :title="t('inbox.mensagem.anexar')"
                    @click="handleAttach"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd" />
                    </svg>
                </button>

                <!-- Textarea -->
                <textarea
                    ref="textareaRef"
                    v-model="body"
                    rows="1"
                    :disabled="isDisabled"
                    :placeholder="isWindowClosed
                        ? t('inbox.janela_24h.fechada')
                        : t('inbox.mensagem.input_placeholder')"
                    class="flex-1 resize-none bg-transparent py-2.5 pr-1 text-sm text-foreground placeholder-foreground-muted outline-none disabled:cursor-not-allowed"
                    :style="{ lineHeight: '22px' }"
                    :aria-label="t('inbox.mensagem.input_placeholder')"
                    :aria-disabled="isDisabled"
                    @input="onInput"
                    @keydown="onKeydown"
                ></textarea>

                <!-- Botão enviar -->
                <button
                    type="button"
                    :disabled="isDisabled || !body.trim() || isOverLimit"
                    class="mr-2 mb-2 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-700 text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :aria-label="t('inbox.mensagem.enviar')"
                    :title="t('inbox.mensagem.enviar')"
                    @click="submit"
                >
                    <!-- Loading spinner -->
                    <svg
                        v-if="store.sendingMessage"
                        class="h-4 w-4 animate-spin"
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <!-- Paper plane -->
                    <svg
                        v-else
                        class="h-4 w-4"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                    </svg>
                </button>
            </div>

            <!-- Rodapé: contador de chars -->
            <div class="flex items-center justify-between mt-1.5 px-1">
                <div class="flex-1">
                    <!-- Erro de envio -->
                    <div
                        v-if="sendError"
                        role="alert"
                        aria-live="assertive"
                        class="text-xs font-medium"
                        :class="sendErrorCode === 'circuit_open' ? 'text-warning-700' : 'text-danger-700'"
                    >
                        {{ sendError }}
                        <span v-if="sendErrorCode === 'janela_fechada' && windowExpiredDetails?.available_templates_count > 0" class="ml-1">
                            ({{ t('inbox.janela_24h.templates_disponiveis', { count: windowExpiredDetails.available_templates_count }) }})
                        </span>
                    </div>
                </div>

                <span
                    class="text-xs"
                    :class="isOverLimit ? 'text-danger-600 font-semibold' : 'text-foreground-muted'"
                    :aria-label="t('inbox.mensagem.char_count', { used: charCount })"
                >
                    {{ charCount }} / {{ CHAR_LIMIT }}
                </span>
            </div>
        </div>

        <!-- Selector de template (quando janela fechada) — placeholder T241 -->
        <div
            v-if="isWindowClosed"
            class="border-t border-border px-4 py-3"
        >
            <p class="text-xs text-foreground-muted italic">
                <!-- T241 (Lote M) implementará o MetaTemplateSelector real -->
                {{ t('inbox.janela_24h.fechada') }}
            </p>
        </div>
    </div>
</template>

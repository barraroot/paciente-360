<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useIaStore } from '@/stores/ia.js';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';

/**
 * **T189 (Fase 18 — US6, R14)** — modal de chat sandbox para teste de Persona.
 *
 * Comportamento:
 *  - half-screen no desktop (max-w-2xl, h-[80vh]) / fullscreen mobile;
 *  - abre uma `PersonaTestSession` ao montar (real OU draft via `personaDraft`);
 *  - stream da resposta da IA via Reverb (canal privado `persona-test.{id}`);
 *  - indicador "IA pensando…" (evento `persona-test.thinking`);
 *  - input texto enter→envia (Shift+Enter quebra linha);
 *  - botão "limpar conversa" (limpa só a render local; sessão segue aberta);
 *  - upload de áudio fica DESABILITADO no MVP — backend ainda processa só texto;
 *  - close revoga o PAT MCP no servidor (FR-051) e libera o canal Reverb;
 *  - a11y: Teleport + role=dialog + focus trap + Esc fecha + retorna foco.
 *
 * `mcp_token` é descartado após o open — o componente NÃO o usa diretamente
 * (a IA roda no servidor, autenticada pelo seu próprio PAT efêmero).
 */
const props = defineProps({
    open: { type: Boolean, required: true },
    persona: { type: Object, required: true },
    personaDraft: { type: Object, default: null },
    useDraft: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'open-history']);

const store = useIaStore();
const modalEl = ref(null);
const activeRef = ref(false);
const composer = ref('');
const sending = ref(false);
const opening = ref(false);
const closing = ref(false);
const iaThinking = ref(false);
const openError = ref(null);
const messagesEl = ref(null);
const channelRef = ref(null);
const localSessionId = ref(null);

const messages = computed(() => store.personaTestMessages);
const sessionStatus = computed(() => store.personaTestSession?.status ?? null);
const canSend = computed(
    () => sessionStatus.value === 'open' && composer.value.trim().length > 0 && !sending.value,
);

useShellFocusTrap(modalEl, activeRef);

watch(
    () => props.open,
    async (next) => {
        if (next) {
            activeRef.value = true;
            await openSession();
        } else {
            await teardown();
        }
    },
    { immediate: false },
);

onMounted(async () => {
    if (props.open) {
        activeRef.value = true;
        await openSession();
    }
});

onUnmounted(async () => {
    await teardown();
});

async function openSession() {
    openError.value = null;
    opening.value = true;
    try {
        const session = await store.openPersonaTestSession(props.persona.id, {
            useDraft: props.useDraft,
            personaDraft: props.useDraft ? props.personaDraft : null,
        });
        localSessionId.value = session.id;
        subscribeEcho(session.id);
        appendSystem(
            props.useDraft
                ? 'Testando a versão em edição (rascunho). Nada aqui afeta produção.'
                : 'Sessão sandbox aberta. Nada aqui é enviado a pacientes nem altera dados reais.',
        );
    } catch (e) {
        openError.value =
            e?.response?.status === 403
                ? 'Você não tem permissão para testar personas.'
                : (e?.response?.data?.message ?? 'Não foi possível abrir a sessão de teste.');
    } finally {
        opening.value = false;
    }
}

function subscribeEcho(sessionId) {
    const echo = window.Echo;
    if (!echo || !sessionId) {
        return;
    }
    try {
        const ch = echo.private(`persona-test.${sessionId}`);
        channelRef.value = ch;

        ch.listen('.persona-test.message', (event) => {
            iaThinking.value = false;
            const msg = event?.message ?? event;
            if (!msg || msg.id == null) return;
            store.appendPersonaTestMessage({
                id: msg.id,
                body: msg.body ?? '',
                direction: msg.direction ?? 'outbound',
                sender_type: msg.sender_type ?? 'ia',
                sandbox: true,
                created_at: msg.created_at ?? new Date().toISOString(),
                kind: event?.kind ?? 'ia',
            });
            scrollToBottom();
        });

        ch.listen('.persona-test.thinking', () => {
            iaThinking.value = true;
        });

        ch.listen('.persona-test.session.closed', () => {
            iaThinking.value = false;
            appendSystem('Esta sessão foi encerrada em outro dispositivo.');
        });
    } catch {
        // Falha no canal não bloqueia o UX — apenas o broadcast em tempo real.
    }
}

async function teardown() {
    activeRef.value = false;
    iaThinking.value = false;
    const id = localSessionId.value;
    if (!id) return;

    try {
        if (window.Echo) {
            window.Echo.leave(`persona-test.${id}`);
        }
    } catch {
        // ignore
    }
    channelRef.value = null;

    if (sessionStatus.value === 'open') {
        closing.value = true;
        try {
            await store.closePersonaTestSession(id);
        } catch {
            // ignora — sessão pode ter sido fechada pelo servidor
        } finally {
            closing.value = false;
        }
    }
    localSessionId.value = null;
    store.personaTestSession = null;
    store.personaTestEchoChannelName = null;
    store.clearPersonaTestMessages();
}

async function sendMessage() {
    if (!canSend.value) return;
    const text = composer.value.trim();
    composer.value = '';
    sending.value = true;
    iaThinking.value = true;
    try {
        await store.sendPersonaTestMessage(localSessionId.value, text);
        // A mensagem do admin é também emitida via broadcast; appendPersonaTestMessage
        // tem dedup por id. Para feedback imediato, otimisticamente:
        store.appendPersonaTestMessage({
            id: `local-${Date.now()}`,
            body: text,
            direction: 'inbound',
            sender_type: 'admin',
            sandbox: true,
            created_at: new Date().toISOString(),
            kind: 'admin',
        });
        scrollToBottom();
    } catch (e) {
        iaThinking.value = false;
        appendSystem(
            e?.response?.data?.message ?? 'Falha ao enviar mensagem. Verifique sua conexão.',
        );
    } finally {
        sending.value = false;
    }
}

function appendSystem(message) {
    store.appendPersonaTestMessage({
        id: `system-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
        body: message,
        direction: 'system',
        sender_type: 'system',
        sandbox: true,
        created_at: new Date().toISOString(),
        kind: 'system',
    });
    scrollToBottom();
}

function clearLocal() {
    store.clearPersonaTestMessages();
    appendSystem('Conversa limpa (apenas localmente). A sessão segue aberta.');
}

async function scrollToBottom() {
    await nextTick();
    const el = messagesEl.value;
    if (!el) return;
    el.scrollTop = el.scrollHeight;
}

function handleEnter(event) {
    if (event.shiftKey) return;
    event.preventDefault();
    sendMessage();
}

function close() {
    emit('close');
}

function openHistory() {
    emit('open-history');
}

function bubbleClasses(kind) {
    if (kind === 'admin') {
        return 'self-end bg-primary-600 text-white';
    }
    if (kind === 'system') {
        return 'self-center bg-amber-50 text-amber-800 text-xs border border-amber-200';
    }
    return 'self-start bg-surface-muted text-foreground';
}

function senderLabel(kind) {
    if (kind === 'admin') return 'Você (como paciente)';
    if (kind === 'system') return 'Sistema';
    return props.persona?.name ?? 'IA';
}

function formatTime(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-stretch justify-center bg-black/40 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="persona-test-chat-title"
            @keydown.esc="close"
        >
            <div
                ref="modalEl"
                class="flex h-full w-full max-w-2xl flex-col bg-white shadow-xl sm:h-[80vh] sm:rounded-2xl"
            >
                <!-- Header -->
                <header
                    class="flex items-start justify-between gap-3 border-b border-border px-5 py-4"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <h2
                                id="persona-test-chat-title"
                                class="text-base font-semibold text-foreground"
                            >
                                Testar persona
                            </h2>
                            <span
                                class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                aria-label="Modo sandbox: nada aqui afeta produção"
                                >Sandbox</span
                            >
                        </div>
                        <p class="mt-0.5 text-sm text-foreground-muted">
                            {{ persona.name }}
                            <span v-if="useDraft" class="ml-1 text-amber-700">(rascunho)</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-foreground-muted hover:bg-surface-muted"
                        aria-label="Fechar chat de teste"
                        @click="close"
                    >
                        ✕
                    </button>
                </header>

                <!-- Erro de abertura -->
                <div
                    v-if="openError"
                    class="m-5 rounded-lg bg-danger-50 p-4 text-sm text-danger-700"
                    role="alert"
                >
                    {{ openError }}
                </div>

                <!-- Loading inicial -->
                <div
                    v-else-if="opening"
                    class="flex flex-1 items-center justify-center text-sm text-foreground-muted"
                >
                    Abrindo sessão sandbox…
                </div>

                <!-- Mensagens -->
                <section
                    v-else
                    ref="messagesEl"
                    class="flex flex-1 flex-col gap-2 overflow-y-auto bg-surface-subtle px-4 py-4"
                    aria-live="polite"
                >
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="flex max-w-[80%] flex-col gap-1 rounded-2xl px-4 py-2 text-sm"
                        :class="bubbleClasses(msg.kind ?? msg.sender_type)"
                    >
                        <span class="text-[11px] uppercase tracking-wide opacity-70">{{
                            senderLabel(msg.kind ?? msg.sender_type)
                        }}</span>
                        <p class="whitespace-pre-wrap">{{ msg.body }}</p>
                        <span class="self-end text-[10px] opacity-60">{{
                            formatTime(msg.created_at)
                        }}</span>
                    </div>

                    <div
                        v-if="iaThinking"
                        class="flex max-w-[80%] flex-col gap-1 self-start rounded-2xl bg-surface-muted px-4 py-2 text-sm text-foreground-muted"
                        aria-live="polite"
                    >
                        <span class="text-[11px] uppercase tracking-wide opacity-70">
                            {{ persona.name }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2 w-2 animate-bounce rounded-full bg-foreground-muted" />
                            <span
                                class="h-2 w-2 animate-bounce rounded-full bg-foreground-muted"
                                style="animation-delay: 120ms"
                            />
                            <span
                                class="h-2 w-2 animate-bounce rounded-full bg-foreground-muted"
                                style="animation-delay: 240ms"
                            />
                            <span class="ml-2 text-xs">pensando…</span>
                        </span>
                    </div>
                </section>

                <!-- Composer -->
                <footer class="border-t border-border bg-white px-4 py-3">
                    <div class="flex items-end gap-2">
                        <textarea
                            v-model="composer"
                            rows="2"
                            :disabled="sessionStatus !== 'open' || sending"
                            placeholder="Escreva como se você fosse o paciente…"
                            aria-label="Mensagem do paciente no sandbox"
                            class="block w-full resize-none rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none disabled:opacity-50"
                            @keydown.enter="handleEnter"
                        ></textarea>
                        <button
                            type="button"
                            :disabled="!canSend"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            @click="sendMessage"
                        >
                            Enviar
                        </button>
                    </div>
                    <!--
                        Áudio (STT) — UI presente para FR-039/T189 mas DESABILITADO no MVP:
                        o controller ainda processa só texto. Quando US4 inbound STT for
                        liberado no sandbox, basta habilitar o input e adicionar
                        `content_type:'audio'` no payload do store.
                    -->
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2 text-foreground-muted">
                            <button
                                type="button"
                                disabled
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-foreground-muted opacity-50"
                                title="Envio de áudio chega na próxima entrega da multimídia"
                            >
                                🎙️ Enviar áudio (em breve)
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 text-xs text-foreground-muted hover:bg-surface-muted"
                                @click="clearLocal"
                            >
                                Limpar conversa
                            </button>
                        </div>
                        <button
                            type="button"
                            class="text-xs font-medium text-primary-600 hover:underline"
                            @click="openHistory"
                        >
                            Histórico de sessões
                        </button>
                    </div>
                    <p v-if="closing" class="mt-2 text-xs text-foreground-muted">
                        Encerrando sessão…
                    </p>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

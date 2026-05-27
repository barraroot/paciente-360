<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import { useInboxStore } from '@/stores/inbox.js';
import { useAuthStore } from '@/stores/auth.js';
import { useReverbConnection } from '@/composables/useReverbConnection.js';
import { useLongPollingFallback } from '@/composables/useLongPollingFallback.js';
import { useInboxFilters } from '@/composables/useInboxFilters.js';

import InboxFilters from '@/components/Inbox/InboxFilters.vue';
import InboxSearch from '@/components/Inbox/InboxSearch.vue';
import ConversationListItem from '@/components/Inbox/ConversationListItem.vue';
import ConversationDetail from '@/components/Inbox/ConversationDetail.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const store = useInboxStore();
const auth = useAuthStore();

// ─── Filtros e busca ──────────────────────────────────────────────────────────

const { filters, clearFilters, applyFilter, toggleFilter, filtersActive } = useInboxFilters();

// Sincroniza filtros locais com a store para que as actions usem os mesmos filtros
watch(
    filters,
    (newFilters) => {
        Object.assign(store.filters, newFilters);
        loadConversations();
    },
    { deep: true, immediate: false },
);

// ─── Conversa selecionada ─────────────────────────────────────────────────────

const selectedId = computed(() => store.selectedConversationId);
const selectedConversation = computed(() => store.selectedConversation);

/** Mobile: mostra detalhe quando há conversa selecionada */
const showDetailOnMobile = ref(false);

function selectConversation(id) {
    store.selectConversation(id);
    showDetailOnMobile.value = true;

    // Sincroniza com URL query param
    router.replace({
        query: { ...route.query, id },
    });
}

function backToList() {
    showDetailOnMobile.value = false;
}

// Restaura seleção ao navegar para /panel/inbox/conversa/:id ou ?id=
onMounted(() => {
    const idFromParam = route.params.id;
    const idFromQuery = route.query.id;
    const id = idFromParam ?? idFromQuery;

    if (id) {
        store.selectConversation(id);
        showDetailOnMobile.value = true;
    }
});

// Watch de mudança de rota para sync bidirecional
watch(
    () => route.params.id,
    (newId) => {
        if (newId && String(newId) !== String(store.selectedConversationId)) {
            store.selectConversation(newId);
            showDetailOnMobile.value = true;
        }
    },
);

// ─── Carregamento de conversas ────────────────────────────────────────────────

let loadDebounce = null;

async function loadConversations() {
    clearTimeout(loadDebounce);
    loadDebounce = setTimeout(async () => {
        try {
            await store.loadConversations({ replace: true });
        } catch {
            // Erro já fica em store.error
        }
    }, 100);
}

// ─── Reverb e long-polling ────────────────────────────────────────────────────

const tenantId = computed(() => auth.currentTenantId);

const INBOX_REFRESH_EVENTS = [
    'mensagem.recebida',
    'mensagem.enviada',
    'conversa.criada',
    'conversa.atribuida',
];

function handleInboxEvent(event) {
    // Sinal apenas: qualquer evento de inbox recarrega a lista (debounced) para
    // refletir preview, não-lidas e ordem. O corpo cifrado nunca trafega pelo WS.
    if (INBOX_REFRESH_EVENTS.includes(event.type)) {
        loadConversations();
    }
}

function handleConversationEvent(cid, event) {
    const { type, payload } = event;

    if (type === 'mensagem.recebida' || type === 'mensagem.enviada') {
        // Sinal: refetch das mensagens da conversa aberta (traz o corpo via API autenticada).
        if (String(cid) === String(selectedId.value)) {
            store.loadMessages(cid);
        }
    } else if (type === 'StatusMensagemAtualizado' && payload?.message_id) {
        // Atualiza status da mensagem na lista local
        const key = String(cid);
        const msgs = store.messagesByConversationId[key];
        if (msgs) {
            const idx = msgs.findIndex((m) => String(m.id) === String(payload.message_id));
            if (idx !== -1) {
                msgs[idx] = { ...msgs[idx], status: payload.status };
            }
        }
    }
}

let reverbConnection = null;

function initReverb() {
    if (!tenantId.value) { return; }

    reverbConnection = useReverbConnection(
        tenantId.value,
        handleInboxEvent,
        handleConversationEvent,
    );

    store.reverbConnected = reverbConnection.connected.value;
}

// Conecta Reverb ao montar (deferred para garantir tenantId)
watch(tenantId, (id) => {
    if (id && !reverbConnection) {
        initReverb();
    }
}, { immediate: true });

// Long-polling fallback
const pollingConnected = computed(() => store.reverbConnected);

const { polling, banner, disablePresenceFeatures } = useLongPollingFallback(
    pollingConnected,
    (since) => store.pollUpdates(since),
);

// Sync store.pollingMode
watch(polling, (val) => {
    store.pollingMode = val;
});

// Subscribe à sala da conversa quando abre
watch(selectedId, (newId, oldId) => {
    if (!reverbConnection) { return; }

    if (oldId) {
        reverbConnection.unsubscribeFromConversation(oldId);
    }
    if (newId) {
        reverbConnection.subscribeToConversation(newId);
    }
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
    // Aplica filtros da URL antes de carregar
    Object.assign(store.filters, filters);
    await loadConversations();
});

onBeforeUnmount(() => {
    if (reverbConnection) {
        reverbConnection.destroy();
        reverbConnection = null;
    }
    clearTimeout(loadDebounce);
});

// ─── Computeds de UI ──────────────────────────────────────────────────────────

const aggregations = computed(() => store.aggregations);

const mineCount = computed(() => aggregations.value.mine ?? 0);
const unassignedCount = computed(() => aggregations.value.unassigned ?? 0);
const totalCount = computed(() => store.pagination.total ?? 0);

const conversations = computed(() => store.conversations);
const isLoading = computed(() => store.loading);
const hasError = computed(() => !!store.error);
const isEmpty = computed(() => !isLoading.value && conversations.value.length === 0);
</script>

<template>
    <div class="flex h-full flex-col bg-surface text-foreground">
        <!-- ── Banner long-polling ─────────────────────────────────────────── -->
        <div
            v-if="polling && banner"
            class="flex items-center justify-center gap-2 bg-warning-100 px-4 py-2 text-xs font-medium text-warning-800"
            role="status"
            aria-live="polite"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            {{ t(banner) }}
        </div>

        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <header class="border-b border-border bg-surface-elevated px-4 py-3 shrink-0">
            <div class="flex items-center justify-between">
                <div>
                    <!-- Breadcrumb -->
                    <nav
                        aria-label="Breadcrumb"
                        class="mb-1 flex items-center gap-1.5 text-xs text-foreground-muted"
                    >
                        <a
                            href="/panel"
                            class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                        >
                            Painel
                        </a>
                        <span aria-hidden="true">›</span>
                        <span class="text-foreground">{{ t('inbox.titulo') }}</span>
                    </nav>
                    <h1 class="text-xl font-bold text-foreground">{{ t('inbox.titulo') }}</h1>
                </div>

                <!-- Counters de aggregation -->
                <div class="hidden sm:flex items-center gap-4 text-xs text-foreground-muted">
                    <span>
                        <span class="font-semibold text-foreground">{{ mineCount }}</span>
                        minhas
                    </span>
                    <span>
                        <span class="font-semibold text-warning-700">{{ unassignedCount }}</span>
                        sem atendente
                    </span>
                    <span>
                        <span class="font-semibold text-foreground">{{ totalCount }}</span>
                        total
                    </span>

                    <!-- Indicador Reverb -->
                    <div class="flex items-center gap-1" :title="store.reverbConnected ? 'Tempo real ativo' : 'Modo polling'">
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="store.reverbConnected ? 'bg-success-500' : 'bg-warning-400'"
                            aria-hidden="true"
                        ></span>
                        <span class="sr-only">{{ store.reverbConnected ? 'Tempo real ativo' : 'Modo polling ativo' }}</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- ── Layout principal 3-pane ────────────────────────────────────── -->
        <div class="flex flex-1 overflow-hidden">

            <!-- ── Pane 1: Filtros (apenas desktop) ───────────────────────── -->
            <!-- Sem v-if: a visibilidade é só responsiva (hidden md:flex). No desktop
                 permanece sempre visível; no mobile nunca aparece. -->
            <InboxFilters
                :filters="filters"
                :aggregations="aggregations"
                :filters-active="filtersActive"
                class="hidden md:flex w-60 shrink-0"
                @toggle-filter="toggleFilter"
                @apply-filter="applyFilter"
                @clear-filters="clearFilters"
            />

            <!-- ── Pane 2: Lista de conversas ──────────────────────────────── -->
            <!-- Sem v-if: no desktop (md+) a lista fica SEMPRE visível mesmo com uma
                 conversa aberta; só no mobile ela é ocultada quando o detalhe assume. -->
            <div
                class="flex flex-col border-r border-border"
                :class="[
                    showDetailOnMobile ? 'hidden md:flex' : 'flex',
                    'md:flex w-full md:w-80 lg:w-96 shrink-0',
                ]"
            >
                <!-- Busca -->
                <InboxSearch
                    v-model="filters.q"
                    :loading="isLoading"
                />

                <!-- Loading skeleton -->
                <div
                    v-if="isLoading && conversations.length === 0"
                    class="flex-1 overflow-y-auto"
                    aria-busy="true"
                    aria-live="polite"
                >
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="flex items-start gap-3 border-b border-border p-3 animate-pulse"
                        aria-hidden="true"
                    >
                        <div class="h-10 w-10 rounded-full bg-surface shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3.5 w-2/3 rounded bg-surface"></div>
                            <div class="h-3 w-full rounded bg-surface"></div>
                        </div>
                    </div>
                </div>

                <!-- Erro -->
                <div
                    v-else-if="hasError"
                    class="p-4"
                    role="alert"
                    aria-live="assertive"
                >
                    <p class="text-sm text-danger-700">{{ t('inbox.errors.carregar_lista') }}</p>
                    <button
                        type="button"
                        class="mt-2 text-xs text-primary-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="loadConversations"
                    >
                        Tentar novamente
                    </button>
                </div>

                <!-- Empty state -->
                <div
                    v-else-if="isEmpty"
                    class="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center"
                    role="status"
                    aria-live="polite"
                >
                    <svg class="h-10 w-10 text-foreground-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                    <p class="text-sm text-foreground-muted">{{ t('inbox.empty_list') }}</p>
                    <button
                        v-if="filtersActive > 0"
                        type="button"
                        class="text-xs text-primary-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="clearFilters"
                    >
                        {{ t('inbox.filtros.limpar') }}
                    </button>
                </div>

                <!-- Lista virtual de conversas -->
                <div
                    v-else
                    class="flex-1 overflow-y-auto"
                    role="list"
                    :aria-label="t('inbox.titulo')"
                >
                    <ConversationListItem
                        v-for="conv in conversations"
                        :key="conv.id"
                        :conversation="conv"
                        :selected="String(conv.id) === String(selectedId)"
                        role="listitem"
                        @click="selectConversation(conv.id)"
                    />
                </div>
            </div>

            <!-- ── Pane 3: Detalhe da conversa ────────────────────────────── -->
            <div
                class="flex-1 overflow-hidden min-w-0"
                :class="[
                    showDetailOnMobile && selectedConversation ? 'flex' : 'hidden md:flex',
                ]"
            >
                <!-- Botão voltar em mobile -->
                <div
                    v-if="showDetailOnMobile"
                    class="md:hidden absolute top-[57px] left-0 z-10 m-2"
                >
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-lg border border-border bg-surface-elevated px-2.5 py-1.5 text-xs font-medium text-foreground shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="backToList"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        Voltar
                    </button>
                </div>

                <!-- Detalhe da conversa selecionada -->
                <ConversationDetail
                    v-if="selectedConversation"
                    class="w-full"
                    :conversation="selectedConversation"
                    :input-disabled="disablePresenceFeatures"
                />

                <!-- Empty state direito -->
                <div
                    v-else
                    class="flex w-full flex-col items-center justify-center gap-4 bg-surface p-8 text-center"
                    role="status"
                >
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-surface-elevated border border-border">
                        <svg class="h-8 w-8 text-foreground-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                        </svg>
                    </div>
                    <p class="text-sm text-foreground-muted">{{ t('inbox.empty_state') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

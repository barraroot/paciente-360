<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInboxStore } from '@/stores/inbox.js';
import { useAuthStore } from '@/stores/auth.js';

const { t } = useI18n();
const store = useInboxStore();
const auth = useAuthStore();

const props = defineProps({
    conversationId: {
        type: [String, Number],
        required: true,
    },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['assigned']);

// ─── Estado ───────────────────────────────────────────────────────────────────

const loading = ref(false);
const toastMessage = ref(null);
const toastType = ref('success'); // 'success' | 'error'

// ─── Toast helpers ────────────────────────────────────────────────────────────

let toastTimer = null;

function showToast(message, type = 'success') {
    toastMessage.value = message;
    toastType.value = type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastMessage.value = null;
    }, 4000);
}

// ─── Usuários atribuíveis ─────────────────────────────────────────────────────

const users = computed(() => store.assignableUsers);
const loadingUsers = computed(() => store.loadingAssignableUsers);

watch(open, async (isOpen) => {
    if (isOpen && users.value.length === 0) {
        await store.loadAssignableUsers();
    }
});

// MAX conversas por atendente (do config — usa 15 como padrão do spec)
const MAX_CONVERSATIONS = 15;

function userLoad(user) {
    return user.active_conversations_count ?? 0;
}

function isAtLimit(user) {
    return userLoad(user) >= MAX_CONVERSATIONS;
}

function userLoadLabel(user) {
    return t('inbox.atribuir.carga', {
        atual: userLoad(user),
        max: MAX_CONVERSATIONS,
    });
}

// ─── Ações de atribuição ──────────────────────────────────────────────────────

async function assignToMe() {
    loading.value = true;
    try {
        const updated = await store.assign(props.conversationId, {
            user_id: auth.user.id,
        });
        const nome = auth.user.name ?? auth.user.email;
        showToast(t('inbox.atribuir.sucesso', { nome }), 'success');
        emit('assigned', updated);
        open.value = false;
    } catch (err) {
        handleAssignError(err);
    } finally {
        loading.value = false;
    }
}

async function assignAuto() {
    loading.value = true;
    try {
        const updated = await store.assign(props.conversationId, { auto: true });
        showToast(t('inbox.atribuir.sucesso', { nome: 'atribuição automática' }), 'success');
        emit('assigned', updated);
        open.value = false;
    } catch (err) {
        handleAssignError(err);
    } finally {
        loading.value = false;
    }
}

async function assignToUser(user) {
    if (isAtLimit(user) || loading.value) { return; }
    loading.value = true;
    try {
        const updated = await store.assign(props.conversationId, {
            user_id: user.id,
        });
        showToast(t('inbox.atribuir.sucesso', { nome: user.name }), 'success');
        emit('assigned', updated);
        open.value = false;
    } catch (err) {
        handleAssignError(err);
    } finally {
        loading.value = false;
    }
}

function handleAssignError(err) {
    const status = err.response?.status;
    const code = err.response?.data?.code ?? err.response?.data?.error;

    if (status === 422 && code === 'user.at_max_limit') {
        showToast(t('inbox.atribuir.erro_at_max'), 'error');
    } else if (status === 403) {
        showToast(t('inbox.atribuir.erro_403'), 'error');
    } else {
        showToast(t('common.error_generic'), 'error');
    }
}

// ─── Fechar / ESC ─────────────────────────────────────────────────────────────

function close() {
    if (!loading.value) { open.value = false; }
}

function onKeydown(event) {
    if (event.key === 'Escape') { close(); }
}

// Limpar role do usuário logado ao exibir
function roleLabel(roles) {
    const map = {
        'admin-clinica': t('inbox.regras.perfis.admin-clinica'),
        medico: t('inbox.regras.perfis.medico'),
        atendente: t('inbox.regras.perfis.atendente'),
        recepcionista: t('inbox.regras.perfis.recepcionista'),
    };
    const first = (roles ?? [])[0];
    return first ? (map[first] ?? first) : '';
}
</script>

<template>
    <Teleport to="body">
        <!-- Toast global -->
        <div
            v-if="toastMessage"
            role="alert"
            aria-live="assertive"
            class="fixed bottom-5 right-5 z-[200] flex items-center gap-2 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium max-w-xs"
            :class="toastType === 'success'
                ? 'border-success-300 bg-success-50 text-success-800'
                : 'border-danger-300 bg-danger-50 text-danger-800'"
        >
            <svg v-if="toastType === 'success'" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <svg v-else class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            {{ toastMessage }}
        </div>

        <!-- Modal overlay -->
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="assign-dialog-title"
            @click.self="close"
            @keydown="onKeydown"
        >
            <div class="w-full max-w-sm rounded-xl border border-border bg-surface-elevated shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 id="assign-dialog-title" class="text-base font-semibold text-foreground">
                        {{ t('inbox.atribuir.titulo') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :aria-label="t('common.cancel')"
                        @click="close"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-3">
                    <!-- Atribuir a mim -->
                    <button
                        type="button"
                        :disabled="loading"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="assignToMe"
                    >
                        <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                        {{ t('inbox.atribuir.atribuir_a_mim') }}
                    </button>

                    <!-- Atribuir automaticamente -->
                    <button
                        type="button"
                        :disabled="loading"
                        :title="t('inbox.atribuir.auto_tooltip')"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="assignAuto"
                    >
                        <svg class="h-4 w-4 text-foreground-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                        </svg>
                        {{ t('inbox.atribuir.auto') }}
                    </button>

                    <!-- Divisor -->
                    <div class="flex items-center gap-3">
                        <div class="flex-1 h-px bg-border" aria-hidden="true"></div>
                        <span class="text-xs text-foreground-muted">{{ t('inbox.atribuir.outro_atendente') }}</span>
                        <div class="flex-1 h-px bg-border" aria-hidden="true"></div>
                    </div>

                    <!-- Lista de atendentes -->
                    <div class="max-h-52 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                        <!-- Loading -->
                        <div
                            v-if="loadingUsers"
                            class="flex items-center justify-center py-6"
                            aria-live="polite"
                            aria-busy="true"
                        >
                            <svg class="h-5 w-5 animate-spin text-primary-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>

                        <!-- Vazio -->
                        <div
                            v-else-if="users.length === 0"
                            class="py-6 text-center text-sm text-foreground-muted"
                        >
                            {{ t('common.no_results') }}
                        </div>

                        <!-- Items -->
                        <template v-else>
                            <button
                                v-for="user in users"
                                :key="user.id"
                                type="button"
                                :disabled="isAtLimit(user) || loading"
                                :title="isAtLimit(user) ? t('inbox.atribuir.atendente_no_limite') : undefined"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                :class="isAtLimit(user)
                                    ? 'cursor-not-allowed opacity-50'
                                    : 'hover:bg-surface'"
                                @click="assignToUser(user)"
                            >
                                <!-- Avatar -->
                                <div
                                    class="h-8 w-8 shrink-0 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-semibold"
                                    aria-hidden="true"
                                >
                                    {{ (user.name ?? '?').slice(0, 2).toUpperCase() }}
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-foreground truncate">{{ user.name }}</p>
                                    <p class="text-xs text-foreground-muted">{{ roleLabel(user.roles) }}</p>
                                </div>

                                <!-- Carga -->
                                <span
                                    class="shrink-0 text-xs font-medium"
                                    :class="isAtLimit(user) ? 'text-danger-600' : 'text-foreground-muted'"
                                >
                                    {{ userLoadLabel(user) }}
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

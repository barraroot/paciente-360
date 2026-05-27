<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { formatRelative, formatDate } from '@/composables/useI18nFormat.js';
import { useAuthStore } from '@/stores/auth.js';
import api from '@/lib/api.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

// ─── Estado ──────────────────────────────────────────────────────────────────

const tokens = ref([]);
const currentTokenId = ref(null);
const loading = ref(false);
const loadError = ref(null);

// Modal de confirmação genérico
const confirmModal = ref({
    open: false,
    title: '',
    message: '',
    action: null,
    actionLoading: false,
});

// ─── Carregamento inicial ────────────────────────────────────────────────────

async function fetchTokens() {
    loading.value = true;
    loadError.value = null;

    try {
        const response = await api.get('/auth/tokens');
        tokens.value = response.data.data ?? [];
        currentTokenId.value = response.data.meta?.current_token_id ?? null;
    } catch {
        loadError.value = t('auth.sessoes.erro_carregar');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchTokens);

// ─── Revogação de token individual ──────────────────────────────────────────

function requestRevoke(token) {
    if (token.is_current) {
        // Revogar sessão atual = logout
        openConfirm({
            title: t('auth.sessoes.revogar'),
            message: t('auth.sessoes.confirmar_revogar'),
            action: () => revokeCurrentSession(),
        });
    } else {
        openConfirm({
            title: t('auth.sessoes.revogar'),
            message: t('auth.sessoes.confirmar_revogar'),
            action: () => revokeToken(token.id),
        });
    }
}

async function revokeToken(tokenId) {
    try {
        await api.delete(`/auth/tokens/${tokenId}`);
        tokens.value = tokens.value.filter((t) => t.id !== tokenId);
    } catch {
        loadError.value = t('common.error_generic');
    }
}

async function revokeCurrentSession() {
    await auth.logout();
    router.push({ name: 'auth.login' });
}

// ─── Sair de todos os dispositivos ──────────────────────────────────────────

function requestLogoutAll() {
    openConfirm({
        title: t('auth.sessoes.logout_all'),
        message: t('auth.sessoes.confirmar_logout_all'),
        action: () => doLogoutAll(),
    });
}

async function doLogoutAll() {
    await auth.logoutAll();
    router.push({ name: 'auth.login' });
}

// ─── Helpers do modal ────────────────────────────────────────────────────────

function openConfirm({ title, message, action }) {
    confirmModal.value = {
        open: true,
        title,
        message,
        action,
        actionLoading: false,
    };
}

function closeConfirm() {
    confirmModal.value.open = false;
}

async function executeConfirm() {
    if (!confirmModal.value.action) {
        return;
    }
    confirmModal.value.actionLoading = true;
    try {
        await confirmModal.value.action();
    } finally {
        confirmModal.value.actionLoading = false;
        confirmModal.value.open = false;
    }
}

// ─── Formatação de datas ─────────────────────────────────────────────────────

function relativeTime(isoDate) {
    if (!isoDate) {
        return '—';
    }
    return formatRelative(isoDate);
}

function expiresAt(isoDate) {
    if (!isoDate) {
        return '—';
    }
    return formatDate(isoDate);
}
</script>

<template>
    <div class="max-w-3xl mx-auto px-4 py-8">
        <!-- Cabeçalho -->
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-foreground">
                {{ t('auth.sessoes.titulo') }}
            </h1>
            <button
                type="button"
                class="rounded-lg border border-danger-500 px-4 py-2 text-sm font-medium text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50"
                :disabled="loading"
                @click="requestLogoutAll"
            >
                {{ t('auth.sessoes.logout_all') }}
            </button>
        </div>

        <!-- Erro de carregamento -->
        <div
            v-if="loadError"
            role="alert"
            aria-live="assertive"
            class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
        >
            {{ loadError }}
        </div>

        <!-- Carregando -->
        <div
            v-if="loading"
            aria-live="polite"
            class="text-sm text-foreground-muted py-8 text-center"
        >
            {{ t('common.loading') }}
        </div>

        <!-- Lista vazia -->
        <div
            v-else-if="!loading && tokens.length === 0"
            class="text-sm text-foreground-muted py-8 text-center"
        >
            {{ t('auth.sessoes.vazio') }}
        </div>

        <!-- Lista de sessões -->
        <ul
            v-else
            class="divide-y divide-border rounded-xl border border-border bg-surface-elevated"
            aria-label="Sessões ativas"
        >
            <li
                v-for="token in tokens"
                :key="token.id"
                class="flex items-start justify-between gap-4 px-6 py-4"
            >
                <div class="min-w-0 flex-1">
                    <!-- Nome + badge sessão atual -->
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-medium text-foreground truncate">
                            {{ token.name }}
                        </span>
                        <span
                            v-if="token.is_current"
                            class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700"
                            aria-label="Sessão atual"
                        >
                            {{ t('auth.sessoes.atual') }}
                        </span>
                    </div>

                    <!-- Token prefix (monospace) -->
                    <p class="text-xs text-foreground-muted font-mono mb-1">
                        {{ token.token_id_prefix }}…
                    </p>

                    <!-- Último uso -->
                    <p class="text-xs text-foreground-subtle">
                        {{
                            token.last_used_at
                                ? t('auth.sessoes.usado_em', {
                                      tempo: relativeTime(token.last_used_at),
                                  })
                                : '—'
                        }}
                    </p>

                    <!-- Expiração -->
                    <p
                        v-if="token.expires_at"
                        class="text-xs text-foreground-subtle"
                        :title="token.expires_at"
                    >
                        {{
                            t('auth.sessoes.expira_em', {
                                data: expiresAt(token.expires_at),
                            })
                        }}
                    </p>
                </div>

                <!-- Ação revogar -->
                <button
                    type="button"
                    class="shrink-0 rounded-lg border border-danger-300 px-3 py-1.5 text-xs font-medium text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500"
                    @click="requestRevoke(token)"
                >
                    {{ t('auth.sessoes.revogar') }}
                </button>
            </li>
        </ul>
    </div>

    <!-- Modal de confirmação -->
    <ConfirmModal
        :open="confirmModal.open"
        :title="confirmModal.title"
        @close="closeConfirm"
        @confirm="executeConfirm"
    >
        <p class="text-sm text-foreground-muted">{{ confirmModal.message }}</p>

        <template #actions>
            <button
                type="button"
                class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="closeConfirm"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="button"
                class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50"
                :disabled="confirmModal.actionLoading"
                @click="executeConfirm"
            >
                {{ confirmModal.actionLoading ? t('common.loading') : t('auth.sessoes.revogar') }}
            </button>
        </template>
    </ConfirmModal>
</template>

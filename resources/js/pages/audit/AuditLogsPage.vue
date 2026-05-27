<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';
import { useAuthStore } from '@/stores/auth.js';
import api from '@/lib/api.js';

const { t } = useI18n();
const { formatDateTime } = useI18nFormat();
const auth = useAuthStore();

// ─── Controle de acesso ───────────────────────────────────────────────────────

const canAccess = computed(
    () =>
        auth.hasRole('admin-clinica') ||
        auth.hasRole('financeiro') ||
        auth.hasRole('super-admin'),
);

// ─── Filtros ──────────────────────────────────────────────────────────────────

const ACTION_OPTIONS = [
    'tenant.registered',
    'user.login.succeeded',
    'user.login.failed',
    'user.login.locked',
    'user.invited',
    'user.invitation.accepted',
    'user.invitation.revoked',
    'user.updated',
    'user.disabled',
    'billing.subscription.created',
    'billing.subscription.updated',
    'billing.subscription.cancelled',
    'billing.ai_hard_cap.updated',
];

const filters = reactive({
    from: '',
    to: '',
    action: '',
    actor_user_id: '',
});

const pendingFilters = reactive({
    from: '',
    to: '',
    action: '',
    actor_user_id: '',
});

function applyFilters() {
    filters.from = pendingFilters.from;
    filters.to = pendingFilters.to;
    filters.action = pendingFilters.action;
    filters.actor_user_id = pendingFilters.actor_user_id;
    fetchLogs(1);
}

function clearFilters() {
    pendingFilters.from = '';
    pendingFilters.to = '';
    pendingFilters.action = '';
    pendingFilters.actor_user_id = '';
    applyFilters();
}

// ─── Export CSV ───────────────────────────────────────────────────────────────

function exportCsv() {
    const params = new URLSearchParams();
    if (filters.from) { params.set('from', filters.from); }
    if (filters.to) { params.set('to', filters.to); }
    if (filters.action) { params.set('action', filters.action); }
    if (filters.actor_user_id) { params.set('actor_user_id', filters.actor_user_id); }
    window.location.href = `/api/v1/audit-logs/export?${params.toString()}`;
}

// ─── Lista de logs ────────────────────────────────────────────────────────────

const logs = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 });

async function fetchLogs(page = 1) {
    loading.value = true;
    error.value = null;

    try {
        const params = { page, per_page: meta.value.per_page };
        if (filters.from) { params.from = filters.from; }
        if (filters.to) { params.to = filters.to; }
        if (filters.action) { params.action = filters.action; }
        if (filters.actor_user_id) { params.actor_user_id = filters.actor_user_id; }

        const response = await api.get('/audit-logs', { params });
        logs.value = response.data.data ?? [];
        meta.value = response.data.meta ?? meta.value;
    } catch (err) {
        const status = err.response?.status;
        if (status === 403) {
            // Backend confirma o que o guard de UI já impede
            error.value = t('common.error_generic');
        } else {
            error.value = t('common.error_generic');
        }
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > meta.value.last_page) { return; }
    fetchLogs(page);
}

onMounted(() => {
    if (canAccess.value) {
        fetchLogs(1);
    }
});

// ─── Modal de detalhes ────────────────────────────────────────────────────────

const detailModal = reactive({
    open: false,
    log: null,
});

function openDetail(log) {
    detailModal.log = log;
    detailModal.open = true;
}

function closeDetail() {
    detailModal.open = false;
    detailModal.log = null;
}

function prettyPayload(payload) {
    if (!payload) { return '{}'; }
    try {
        return JSON.stringify(
            typeof payload === 'string' ? JSON.parse(payload) : payload,
            null,
            2,
        );
    } catch {
        return String(payload);
    }
}

function actorLabel(actor) {
    if (!actor) { return t('audit.list.actor_system'); }
    if (actor.type === 'system') { return t('audit.list.actor_system'); }
    if (actor.type === 'webhook') { return t('audit.list.actor_webhook'); }
    return actor.user_name ?? actor.email ?? t('audit.list.actor_system');
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-7xl">

            <!-- ── Cabeçalho ─────────────────────────────────────────────── -->
            <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ t('audit.list.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-foreground-muted">
                        {{ t('audit.list.subtitle') }}
                    </p>
                </div>
                <button
                    v-if="canAccess"
                    type="button"
                    class="mt-2 inline-flex shrink-0 items-center rounded-lg border border-border bg-surface-elevated px-4 py-2.5 text-sm font-semibold text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 sm:mt-0"
                    @click="exportCsv"
                >
                    {{ t('audit.list.export_csv') }}
                </button>
            </div>

            <!-- ── Acesso negado ──────────────────────────────────────────── -->
            <div
                v-if="!canAccess"
                role="alert"
                aria-live="assertive"
                class="rounded-xl border border-danger-500 bg-danger-50 px-6 py-8 text-center"
            >
                <p class="text-base font-semibold text-danger-700">Acesso negado</p>
                <p class="mt-1 text-sm text-danger-600">
                    Você não tem permissão para visualizar os logs de auditoria.
                </p>
            </div>

            <template v-else>
                <!-- ── Filtros ─────────────────────────────────────────────── -->
                <form
                    class="mb-6 flex flex-col gap-3 rounded-xl border border-border bg-surface-elevated p-4 sm:flex-row sm:flex-wrap sm:items-end"
                    @submit.prevent="applyFilters"
                >
                    <!-- De -->
                    <div class="flex flex-col gap-1 sm:w-44">
                        <label for="filter-from" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('audit.list.filter_from') }}
                        </label>
                        <input
                            id="filter-from"
                            v-model="pendingFilters.from"
                            type="datetime-local"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        />
                    </div>

                    <!-- Até -->
                    <div class="flex flex-col gap-1 sm:w-44">
                        <label for="filter-to" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('audit.list.filter_to') }}
                        </label>
                        <input
                            id="filter-to"
                            v-model="pendingFilters.to"
                            type="datetime-local"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        />
                    </div>

                    <!-- Ação -->
                    <div class="flex flex-col gap-1 sm:w-56">
                        <label for="filter-action" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('audit.list.filter_action') }}
                        </label>
                        <select
                            id="filter-action"
                            v-model="pendingFilters.action"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        >
                            <option value="">— {{ t('audit.list.filter_action') }} —</option>
                            <option
                                v-for="action in ACTION_OPTIONS"
                                :key="action"
                                :value="action"
                            >
                                {{ action }}
                            </option>
                        </select>
                    </div>

                    <!-- Usuário (actor_user_id) -->
                    <div class="flex flex-col gap-1 sm:w-36">
                        <label for="filter-actor" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('audit.list.filter_user') }}
                        </label>
                        <input
                            id="filter-actor"
                            v-model="pendingFilters.actor_user_id"
                            type="number"
                            min="1"
                            placeholder="ID"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        />
                    </div>

                    <!-- Botões -->
                    <div class="flex gap-2 sm:ml-auto">
                        <button
                            type="submit"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        >
                            {{ t('audit.list.filter_apply') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="clearFilters"
                        >
                            {{ t('audit.list.filter_clear') }}
                        </button>
                    </div>
                </form>

                <!-- ── Loading ────────────────────────────────────────────── -->
                <div
                    v-if="loading"
                    class="flex items-center justify-center py-16"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
                </div>

                <!-- ── Erro ───────────────────────────────────────────────── -->
                <div
                    v-else-if="error"
                    role="alert"
                    aria-live="assertive"
                    class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
                >
                    {{ error }}
                </div>

                <!-- ── Tabela ─────────────────────────────────────────────── -->
                <div v-else>
                    <!-- Desktop table -->
                    <div class="hidden overflow-x-auto rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)] sm:block">
                        <table class="w-full text-sm">
                            <thead class="border-b border-border bg-surface text-left">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                        {{ t('audit.list.date') }}
                                    </th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                        {{ t('audit.list.action') }}
                                    </th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                        {{ t('audit.list.user') }}
                                    </th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                        {{ t('audit.list.ip') }}
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                        {{ t('audit.list.column_details') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="log in logs"
                                    :key="log.id"
                                    class="transition hover:bg-surface"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-foreground-muted">
                                        {{ formatDateTime(log.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-foreground">
                                        {{ log.action }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-foreground">
                                        {{ actorLabel(log.actor) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-foreground-muted">
                                        {{ log.ip ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="rounded px-2 py-1 text-xs font-medium text-primary-600 transition hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                            :aria-label="`${t('audit.list.column_details')} — ${log.action}`"
                                            @click="openDetail(log)"
                                        >
                                            Ver
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="logs.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-4 py-12 text-center text-sm text-foreground-muted"
                                    >
                                        {{ t('audit.list.empty') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="space-y-3 sm:hidden">
                        <div
                            v-for="log in logs"
                            :key="log.id"
                            class="rounded-xl border border-border bg-surface-elevated p-4 shadow-[var(--shadow-card)]"
                        >
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <p class="font-mono text-xs font-medium text-foreground">{{ log.action }}</p>
                                <span class="shrink-0 font-mono text-xs text-foreground-muted">{{ log.ip ?? '—' }}</span>
                            </div>
                            <p class="mb-1 text-xs text-foreground-muted">
                                {{ formatDateTime(log.created_at) }}
                            </p>
                            <p class="mb-3 text-sm text-foreground">{{ actorLabel(log.actor) }}</p>
                            <button
                                type="button"
                                class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openDetail(log)"
                            >
                                {{ t('audit.list.column_details') }}
                            </button>
                        </div>
                        <div
                            v-if="logs.length === 0"
                            class="py-12 text-center text-sm text-foreground-muted"
                        >
                            {{ t('audit.list.empty') }}
                        </div>
                    </div>

                    <!-- ── Paginação ───────────────────────────────────────── -->
                    <div
                        v-if="meta.last_page > 1"
                        class="mt-4 flex items-center justify-between"
                        aria-label="Paginação"
                    >
                        <p class="text-xs text-foreground-muted">
                            Página {{ meta.current_page }} de {{ meta.last_page }} ({{ meta.total }} registros)
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="meta.current_page <= 1"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="goToPage(meta.current_page - 1)"
                            >
                                {{ t('common.back') }}
                            </button>
                            <button
                                type="button"
                                :disabled="meta.current_page >= meta.last_page"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="goToPage(meta.current_page + 1)"
                            >
                                {{ t('common.next') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ─── Modal de detalhes ────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="detailModal.open && detailModal.log"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="detail-modal-title"
                @click.self="closeDetail"
                @keydown.escape="closeDetail"
            >
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-border bg-surface-elevated p-6 shadow-xl">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <h2 id="detail-modal-title" class="text-lg font-semibold text-foreground">
                            {{ t('audit.list.detail_modal_title') }}
                        </h2>
                        <button
                            type="button"
                            class="rounded p-1 text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            aria-label="Fechar"
                            @click="closeDetail"
                        >
                            &#10005;
                        </button>
                    </div>

                    <!-- Metadados -->
                    <dl class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('audit.list.action') }}
                            </dt>
                            <dd class="mt-0.5 font-mono text-sm text-foreground">
                                {{ detailModal.log.action }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('audit.list.date') }}
                            </dt>
                            <dd class="mt-0.5 text-sm text-foreground">
                                {{ formatDateTime(detailModal.log.created_at) }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('audit.list.user') }}
                            </dt>
                            <dd class="mt-0.5 text-sm text-foreground">
                                {{ actorLabel(detailModal.log.actor) }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('audit.list.ip') }}
                            </dt>
                            <dd class="mt-0.5 font-mono text-sm text-foreground">
                                {{ detailModal.log.ip ?? '—' }}
                            </dd>
                        </div>

                        <div v-if="detailModal.log.request_id">
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('audit.list.detail_request_id') }}
                            </dt>
                            <dd class="mt-0.5 break-all font-mono text-xs text-foreground">
                                {{ detailModal.log.request_id }}
                            </dd>
                        </div>

                        <div v-if="detailModal.log.user_agent">
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('audit.list.detail_user_agent') }}
                            </dt>
                            <dd class="mt-0.5 break-all text-xs text-foreground-muted">
                                {{ detailModal.log.user_agent }}
                            </dd>
                        </div>

                        <div v-if="detailModal.log.auditable">
                            <dt class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                {{ t('audit.list.detail_auditable') }}
                            </dt>
                            <dd class="mt-0.5 font-mono text-xs text-foreground">
                                {{ detailModal.log.auditable.type }}
                                #{{ detailModal.log.auditable.id }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Payload -->
                    <div>
                        <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('audit.list.detail_payload') }}
                        </p>
                        <pre class="overflow-x-auto rounded-lg border border-border bg-surface p-3 text-xs text-foreground leading-relaxed">{{ prettyPayload(detailModal.log.payload) }}</pre>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                            @click="closeDetail"
                        >
                            {{ t('common.cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </main>
</template>

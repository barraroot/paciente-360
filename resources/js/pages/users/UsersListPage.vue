<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';
import api from '@/lib/api.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import RoleChip from '@/components/users/RoleChip.vue';

const { t } = useI18n();
const { formatDate } = useI18nFormat();
const router = useRouter();

// ─── Tabs ────────────────────────────────────────────────────────────────────

const activeTab = ref('users'); // 'users' | 'invitations'

// ─── Estado da lista de usuários ─────────────────────────────────────────────

const users = ref([]);
const usersLoading = ref(false);
const usersError = ref(null);
const usersMeta = ref({ page: 1, per_page: 15, total: 0 });

const filters = reactive({
    status: '',
    role: '',
    search: '',
});

const STATUS_OPTIONS = [
    { value: '', label: t('users.list.filter_status_all') },
    { value: 'active', label: t('users.list.status_active') },
    { value: 'invited', label: t('users.list.status_invited') },
    { value: 'inactive', label: t('users.list.status_inactive') },
];

const ROLE_OPTIONS = [
    { value: '', label: t('users.list.filter_role_all') },
    { value: 'admin-clinica', label: t('users.invite.role_options.admin-clinica') },
    { value: 'medico', label: t('users.invite.role_options.medico') },
    { value: 'atendente', label: t('users.invite.role_options.atendente') },
    { value: 'recepcionista', label: t('users.invite.role_options.recepcionista') },
    { value: 'financeiro', label: t('users.invite.role_options.financeiro') },
];

async function fetchUsers(page = 1) {
    usersLoading.value = true;
    usersError.value = null;

    try {
        const params = { page, per_page: usersMeta.value.per_page };
        if (filters.status) { params.status = filters.status; }
        if (filters.role) { params.role = filters.role; }
        if (filters.search) { params.search = filters.search; }

        const response = await api.get('/users', { params });
        users.value = response.data.data ?? [];
        usersMeta.value = response.data.meta ?? usersMeta.value;
    } catch {
        usersError.value = t('common.error_generic');
    } finally {
        usersLoading.value = false;
    }
}

// Debounce para o campo search
let searchTimer = null;
watch(
    () => filters.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => fetchUsers(1), 350);
    },
);

watch([() => filters.status, () => filters.role], () => fetchUsers(1));

onMounted(() => fetchUsers(1));

// ─── Paginação ───────────────────────────────────────────────────────────────

const totalPages = computed(() => Math.ceil(usersMeta.value.total / usersMeta.value.per_page) || 1);
const currentPage = computed(() => usersMeta.value.page);

function goToPage(page) {
    if (page < 1 || page > totalPages.value) { return; }
    usersMeta.value.page = page;
    fetchUsers(page);
}

// ─── Modal de edição ─────────────────────────────────────────────────────────

const editModal = reactive({
    open: false,
    userId: null,
    name: '',
    roles: [],
    loading: false,
    error: null,
    validationErrors: {},
});

const ALL_ROLES = ['admin-clinica', 'medico', 'atendente', 'recepcionista', 'financeiro'];

function openEditModal(user) {
    editModal.open = true;
    editModal.userId = user.id;
    editModal.name = user.name;
    editModal.roles = Array.isArray(user.roles) ? [...user.roles] : [];
    editModal.loading = false;
    editModal.error = null;
    editModal.validationErrors = {};
}

function closeEditModal() {
    editModal.open = false;
}

function toggleRole(role) {
    const idx = editModal.roles.indexOf(role);
    if (idx === -1) {
        editModal.roles.push(role);
    } else {
        editModal.roles.splice(idx, 1);
    }
}

async function submitEdit() {
    editModal.loading = true;
    editModal.error = null;
    editModal.validationErrors = {};

    try {
        await api.patch(`/users/${editModal.userId}`, {
            name: editModal.name,
            roles: editModal.roles,
        });
        closeEditModal();
        fetchUsers(currentPage.value);
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 409 && body?.error === 'last_admin') {
            editModal.error = t('users.list.last_admin_warning');
        } else if (status === 422 && body?.errors) {
            editModal.validationErrors = body.errors;
        } else {
            editModal.error = t('common.error_generic');
        }
    } finally {
        editModal.loading = false;
    }
}

function fieldEditError(field) {
    return editModal.validationErrors[field]?.[0] ?? null;
}

// ─── Desativar usuário ────────────────────────────────────────────────────────

const disableModal = reactive({
    open: false,
    userId: null,
    userName: '',
    loading: false,
    error: null,
});

function openDisableModal(user) {
    disableModal.open = true;
    disableModal.userId = user.id;
    disableModal.userName = user.name;
    disableModal.loading = false;
    disableModal.error = null;
}

function closeDisableModal() {
    disableModal.open = false;
}

async function confirmDisable() {
    disableModal.loading = true;
    disableModal.error = null;

    try {
        await api.delete(`/users/${disableModal.userId}`);
        closeDisableModal();
        fetchUsers(currentPage.value);
    } catch (err) {
        const status = err.response?.status;
        const body = err.response?.data;

        if (status === 409 && body?.error === 'last_admin') {
            disableModal.error = t('users.list.last_admin_warning');
        } else {
            disableModal.error = t('common.error_generic');
        }
        disableModal.loading = false;
    }
}

// ─── Convites pendentes ───────────────────────────────────────────────────────

const invitations = ref([]);
const invitationsLoading = ref(false);
const invitationsError = ref(null);

async function fetchInvitations() {
    invitationsLoading.value = true;
    invitationsError.value = null;

    try {
        const response = await api.get('/users/invitations');
        invitations.value = response.data.data ?? response.data ?? [];
    } catch {
        invitationsError.value = t('common.error_generic');
    } finally {
        invitationsLoading.value = false;
    }
}

watch(activeTab, (tab) => {
    if (tab === 'invitations' && invitations.value.length === 0) {
        fetchInvitations();
    }
});

// ─── Revogar convite ──────────────────────────────────────────────────────────

const revokeModal = reactive({
    open: false,
    invitationId: null,
    email: '',
    loading: false,
    error: null,
});

function openRevokeModal(invitation) {
    revokeModal.open = true;
    revokeModal.invitationId = invitation.id;
    revokeModal.email = invitation.email;
    revokeModal.loading = false;
    revokeModal.error = null;
}

function closeRevokeModal() {
    revokeModal.open = false;
}

async function confirmRevoke() {
    revokeModal.loading = true;
    revokeModal.error = null;

    try {
        await api.delete(`/users/invitations/${revokeModal.invitationId}`);
        closeRevokeModal();
        fetchInvitations();
    } catch (err) {
        const status = err.response?.status;
        if (status === 410) {
            revokeModal.error = t('users.accept_invitation.expired');
            fetchInvitations();
        } else {
            revokeModal.error = t('common.error_generic');
        }
        revokeModal.loading = false;
    }
}

// ─── Status label ─────────────────────────────────────────────────────────────

function statusLabel(status) {
    const map = {
        active: t('users.list.status_active'),
        invited: t('users.list.status_invited'),
        inactive: t('users.list.status_inactive'),
    };
    return map[status] ?? status;
}

function statusClass(status) {
    const map = {
        active: 'text-success-700 bg-success-50',
        invited: 'text-warning-700 bg-warning-50',
        inactive: 'text-foreground-muted bg-surface',
    };
    return map[status] ?? 'text-foreground-muted';
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4">
        <div class="mx-auto max-w-6xl">

            <!-- ── Cabeçalho ─────────────────────────────────────────────── -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-2xl font-bold text-foreground">
                    {{ t('users.list.title') }}
                </h1>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="router.push({ name: 'users.invite' })"
                >
                    {{ t('users.list.invite_cta') }}
                </button>
            </div>

            <!-- ── Tabs ───────────────────────────────────────────────────── -->
            <div class="mb-6 flex gap-0 border-b border-border" role="tablist">
                <button
                    role="tab"
                    :aria-selected="activeTab === 'users'"
                    class="px-4 py-2.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    :class="activeTab === 'users'
                        ? 'border-b-2 border-primary-700 text-primary-700'
                        : 'text-foreground-muted hover:text-foreground'"
                    @click="activeTab = 'users'"
                >
                    {{ t('users.list.title') }}
                </button>
                <button
                    role="tab"
                    :aria-selected="activeTab === 'invitations'"
                    class="px-4 py-2.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    :class="activeTab === 'invitations'
                        ? 'border-b-2 border-primary-700 text-primary-700'
                        : 'text-foreground-muted hover:text-foreground'"
                    @click="activeTab = 'invitations'"
                >
                    {{ t('users.list.tab_invitations') }}
                </button>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- Tab: Usuários                                                    -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'users'">

                <!-- Filtros -->
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <!-- Status -->
                    <div class="flex flex-col gap-1">
                        <label for="filter-status" class="text-xs font-medium text-foreground-muted uppercase tracking-wide">
                            {{ t('users.list.filter_status') }}
                        </label>
                        <select
                            id="filter-status"
                            v-model="filters.status"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        >
                            <option
                                v-for="opt in STATUS_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Papel -->
                    <div class="flex flex-col gap-1">
                        <label for="filter-role" class="text-xs font-medium text-foreground-muted uppercase tracking-wide">
                            {{ t('users.list.filter_role') }}
                        </label>
                        <select
                            id="filter-role"
                            v-model="filters.role"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        >
                            <option
                                v-for="opt in ROLE_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Busca -->
                    <div class="flex flex-col gap-1 sm:flex-1">
                        <label for="filter-search" class="text-xs font-medium text-foreground-muted uppercase tracking-wide">
                            {{ t('common.search') }}
                        </label>
                        <input
                            id="filter-search"
                            v-model="filters.search"
                            type="search"
                            :placeholder="t('users.list.search_placeholder')"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                        />
                    </div>
                </div>

                <!-- Loading -->
                <div
                    v-if="usersLoading"
                    class="py-12 flex items-center justify-center"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <span class="text-foreground-muted text-sm">{{ t('common.loading') }}</span>
                </div>

                <!-- Erro -->
                <div
                    v-else-if="usersError"
                    role="alert"
                    aria-live="assertive"
                    class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-500"
                >
                    {{ usersError }}
                </div>

                <!-- Tabela (desktop) / Cards (mobile) -->
                <div v-else>
                    <!-- Desktop table -->
                    <div class="hidden sm:block overflow-x-auto rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)]">
                        <table class="w-full text-sm">
                            <thead class="border-b border-border bg-surface text-left">
                                <tr>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_name') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_email') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_roles') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_status') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_last_login') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide text-right">
                                        {{ t('users.list.column_actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="user in users"
                                    :key="user.id"
                                    class="transition hover:bg-surface"
                                >
                                    <td class="px-4 py-3 font-medium text-foreground">
                                        {{ user.name }}
                                    </td>
                                    <td class="px-4 py-3 text-foreground-muted">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <RoleChip
                                                v-for="role in (user.roles ?? [])"
                                                :key="role"
                                                :role="role"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="statusClass(user.status)"
                                        >
                                            {{ statusLabel(user.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-foreground-muted text-xs">
                                        {{ user.last_login_at ? formatDate(user.last_login_at) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                class="rounded px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                                                :aria-label="`${t('users.list.action_edit')} ${user.name}`"
                                                @click="openEditModal(user)"
                                            >
                                                {{ t('users.list.action_edit') }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                                                :aria-label="`${t('users.list.action_disable')} ${user.name}`"
                                                @click="openDisableModal(user)"
                                            >
                                                {{ t('users.list.action_disable') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="users.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-foreground-muted">
                                        {{ t('common.no_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="sm:hidden space-y-3">
                        <div
                            v-for="user in users"
                            :key="user.id"
                            class="rounded-xl border border-border bg-surface-elevated p-4 shadow-[var(--shadow-card)]"
                        >
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div>
                                    <p class="font-medium text-foreground text-sm">{{ user.name }}</p>
                                    <p class="text-xs text-foreground-muted mt-0.5">{{ user.email }}</p>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium shrink-0"
                                    :class="statusClass(user.status)"
                                >
                                    {{ statusLabel(user.status) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-1 mb-3">
                                <RoleChip
                                    v-for="role in (user.roles ?? [])"
                                    :key="role"
                                    :role="role"
                                />
                            </div>
                            <p class="text-xs text-foreground-muted mb-3">
                                {{ t('users.list.column_last_login') }}:
                                {{ user.last_login_at ? formatDate(user.last_login_at) : '—' }}
                            </p>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="openEditModal(user)"
                                >
                                    {{ t('users.list.action_edit') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-danger-300 px-3 py-1.5 text-xs font-medium text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="openDisableModal(user)"
                                >
                                    {{ t('users.list.action_disable') }}
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="users.length === 0"
                            class="text-center text-sm text-foreground-muted py-10"
                        >
                            {{ t('common.no_results') }}
                        </div>
                    </div>

                    <!-- Paginação -->
                    <div
                        v-if="totalPages > 1"
                        class="mt-4 flex items-center justify-between"
                        aria-label="Paginação"
                    >
                        <p class="text-xs text-foreground-muted">
                            Página {{ currentPage }} de {{ totalPages }} ({{ usersMeta.total }} usuários)
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="currentPage <= 1"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="goToPage(currentPage - 1)"
                            >
                                {{ t('common.back') }}
                            </button>
                            <button
                                type="button"
                                :disabled="currentPage >= totalPages"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="goToPage(currentPage + 1)"
                            >
                                {{ t('common.next') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- Tab: Convites pendentes                                          -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'invitations'">
                <div
                    v-if="invitationsLoading"
                    class="py-12 flex items-center justify-center"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <span class="text-foreground-muted text-sm">{{ t('common.loading') }}</span>
                </div>

                <div
                    v-else-if="invitationsError"
                    role="alert"
                    aria-live="assertive"
                    class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-500"
                >
                    {{ invitationsError }}
                </div>

                <div v-else>
                    <!-- Desktop table -->
                    <div class="hidden sm:block overflow-x-auto rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)]">
                        <table class="w-full text-sm">
                            <thead class="border-b border-border bg-surface text-left">
                                <tr>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_email') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_roles') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide">
                                        {{ t('users.list.column_expires') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold text-foreground-muted text-xs uppercase tracking-wide text-right">
                                        {{ t('users.list.column_actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="invitation in invitations"
                                    :key="invitation.id"
                                    class="transition hover:bg-surface"
                                >
                                    <td class="px-4 py-3 text-foreground">{{ invitation.email }}</td>
                                    <td class="px-4 py-3">
                                        <RoleChip :role="invitation.intended_role" />
                                    </td>
                                    <td class="px-4 py-3 text-foreground-muted text-xs">
                                        {{ invitation.expires_at ? formatDate(invitation.expires_at) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="rounded px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                                            @click="openRevokeModal(invitation)"
                                        >
                                            {{ t('users.list.action_revoke') }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="invitations.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-sm text-foreground-muted">
                                        {{ t('users.list.no_pending_invitations') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards de convites -->
                    <div class="sm:hidden space-y-3">
                        <div
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="rounded-xl border border-border bg-surface-elevated p-4 shadow-[var(--shadow-card)]"
                        >
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <p class="font-medium text-foreground text-sm">{{ invitation.email }}</p>
                                <RoleChip :role="invitation.intended_role" />
                            </div>
                            <p class="text-xs text-foreground-muted mb-3">
                                {{ t('users.list.column_expires') }}:
                                {{ invitation.expires_at ? formatDate(invitation.expires_at) : '—' }}
                            </p>
                            <button
                                type="button"
                                class="rounded-lg border border-danger-300 px-3 py-1.5 text-xs font-medium text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="openRevokeModal(invitation)"
                            >
                                {{ t('users.list.action_revoke') }}
                            </button>
                        </div>
                        <div
                            v-if="invitations.length === 0"
                            class="text-center text-sm text-foreground-muted py-10"
                        >
                            {{ t('users.list.no_pending_invitations') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Modal de edição ───────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="editModal.open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="edit-modal-title"
                @click.self="closeEditModal"
                @keydown.escape="closeEditModal"
            >
                <div class="bg-surface-elevated rounded-xl shadow-xl w-full max-w-md p-6 border border-border">
                    <h2 id="edit-modal-title" class="text-lg font-semibold text-foreground mb-4">
                        {{ t('users.list.action_edit') }}
                    </h2>

                    <!-- Erro -->
                    <div
                        v-if="editModal.error"
                        role="alert"
                        aria-live="assertive"
                        class="mb-4 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-500"
                    >
                        {{ editModal.error }}
                    </div>

                    <form @submit.prevent="submitEdit">
                        <!-- Nome -->
                        <div class="mb-4">
                            <label for="edit-name" class="mb-1.5 block text-sm font-medium text-foreground">
                                {{ t('users.list.column_name') }}
                            </label>
                            <input
                                id="edit-name"
                                v-model="editModal.name"
                                type="text"
                                required
                                autocomplete="name"
                                :aria-invalid="!!fieldEditError('name')"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                :class="{ 'border-danger-500 focus:border-danger-500': fieldEditError('name') }"
                            />
                            <p v-if="fieldEditError('name')" role="alert" class="mt-1 text-xs text-danger-500">
                                {{ fieldEditError('name') }}
                            </p>
                        </div>

                        <!-- Papéis (checkboxes) -->
                        <div class="mb-6">
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium text-foreground">
                                    {{ t('users.list.column_roles') }}
                                </legend>
                                <div class="space-y-2">
                                    <label
                                        v-for="role in ALL_ROLES"
                                        :key="role"
                                        class="flex items-center gap-2.5 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="role"
                                            :checked="editModal.roles.includes(role)"
                                            class="h-4 w-4 rounded border-border text-primary-600 accent-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                            @change="toggleRole(role)"
                                        />
                                        <span class="text-sm text-foreground">
                                            {{ t(`users.invite.role_options.${role}`) }}
                                        </span>
                                    </label>
                                </div>
                            </fieldset>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                @click="closeEditModal"
                            >
                                {{ t('common.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="editModal.loading"
                                class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ editModal.loading ? t('common.loading') : t('common.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal de desativação ──────────────────────────────────────── -->
        <ConfirmModal
            :open="disableModal.open"
            :title="t('users.list.disable_confirm')"
            @close="closeDisableModal"
            @confirm="confirmDisable"
        >
            <p class="text-sm text-foreground-muted mb-2">
                {{ disableModal.userName }}
            </p>
            <div
                v-if="disableModal.error"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-500"
            >
                {{ disableModal.error }}
            </div>
            <template #actions>
                <button
                    type="button"
                    :disabled="disableModal.loading"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmDisable"
                >
                    {{ disableModal.loading ? t('common.loading') : t('users.list.action_disable') }}
                </button>
            </template>
        </ConfirmModal>

        <!-- ─── Modal de revogação ────────────────────────────────────────── -->
        <ConfirmModal
            :open="revokeModal.open"
            :title="t('users.list.action_revoke')"
            @close="closeRevokeModal"
            @confirm="confirmRevoke"
        >
            <p class="text-sm text-foreground-muted mb-2">{{ revokeModal.email }}</p>
            <div
                v-if="revokeModal.error"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-500"
            >
                {{ revokeModal.error }}
            </div>
            <template #actions>
                <button
                    type="button"
                    :disabled="revokeModal.loading"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmRevoke"
                >
                    {{ revokeModal.loading ? t('common.loading') : t('users.list.action_revoke') }}
                </button>
            </template>
        </ConfirmModal>
    </main>
</template>

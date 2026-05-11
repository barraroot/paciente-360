<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useI18nFormat } from '@/composables/useI18nFormat.js';
import { usePacienteSearch } from '@/composables/usePacienteSearch.js';
import { useAuthStore } from '@/stores/auth.js';
import api from '@/lib/api.js';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';

const { t } = useI18n();
const { formatDateTime } = useI18nFormat();
const router = useRouter();
const auth = useAuthStore();

// ─── Filtros ──────────────────────────────────────────────────────────────────

const filters = ref({
    q: '',
    status: '',
    'tag[]': [],
    profissional_responsavel_id: '',
    origem: '',
    page: 1,
    per_page: 15,
});

const { results, loading, error, refetch } = usePacienteSearch(filters);

const pacientes = computed(() => results.value.data ?? []);
const meta = computed(() => results.value.meta ?? {});
const totalPages = computed(() => meta.value.last_page ?? 1);
const currentPage = computed(() => meta.value.page ?? 1);

// ─── Opções de filtro ─────────────────────────────────────────────────────────

const STATUS_OPTIONS = [
    { value: '', label: t('paciente.list.filters.status') + ': Todos' },
    { value: 'lead', label: t('paciente.status.lead') },
    { value: 'ativo', label: t('paciente.status.ativo') },
    { value: 'inativo', label: t('paciente.status.inativo') },
    { value: 'bloqueado', label: t('paciente.status.bloqueado') },
];

const ORIGEM_OPTIONS = [
    { value: '', label: t('paciente.list.filters.origem') + ': Todos' },
    { value: 'site', label: 'Site' },
    { value: 'indicacao', label: 'Indicação' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'telefone', label: 'Telefone' },
    { value: 'presencial', label: 'Presencial' },
    { value: 'outro', label: 'Outro' },
];

// ─── Tags async ───────────────────────────────────────────────────────────────

const tagsDisponiveis = ref([]);
const tagsLoading = ref(false);

async function carregarTags() {
    tagsLoading.value = true;
    try {
        const { data } = await api.get('/tags');
        tagsDisponiveis.value = data.data ?? data ?? [];
    } catch {
        // Falha silenciosa — filtro por tag fica sem opções.
    } finally {
        tagsLoading.value = false;
    }
}

// ─── Profissionais async ──────────────────────────────────────────────────────

const profissionais = ref([]);

async function carregarProfissionais() {
    try {
        const { data } = await api.get('/users', { params: { role: 'medico', per_page: 100 } });
        profissionais.value = data.data ?? data ?? [];
    } catch {
        profissionais.value = [];
    }
}

onMounted(async () => {
    await Promise.all([carregarTags(), carregarProfissionais()]);
});

// ─── Ações de filtro ──────────────────────────────────────────────────────────

function clearFilters() {
    filters.value = {
        q: '',
        status: '',
        'tag[]': [],
        profissional_responsavel_id: '',
        origem: '',
        page: 1,
        per_page: 15,
    };
}

function toggleTag(tagId) {
    const arr = filters.value['tag[]'];
    const idx = arr.indexOf(tagId);
    if (idx === -1) {
        filters.value['tag[]'] = [...arr, tagId];
    } else {
        filters.value['tag[]'] = arr.filter((t) => t !== tagId);
    }
    filters.value.page = 1;
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) { return; }
    filters.value.page = page;
    // usePacienteSearch detecta a mudança via watch e faz refetch
}

// ─── Status badge ─────────────────────────────────────────────────────────────

function statusLabel(status) {
    const map = {
        lead: t('paciente.status.lead'),
        ativo: t('paciente.status.ativo'),
        inativo: t('paciente.status.inativo'),
        bloqueado: t('paciente.status.bloqueado'),
    };
    return map[status] ?? status;
}

function statusClass(status) {
    const map = {
        lead: 'bg-warning-50 text-warning-700',
        ativo: 'bg-success-50 text-success-700',
        inativo: 'bg-surface text-foreground-muted',
        bloqueado: 'bg-danger-50 text-danger-700',
    };
    return map[status] ?? 'bg-surface text-foreground-muted';
}

// ─── Convênio principal ───────────────────────────────────────────────────────

function convenioLabel(paciente) {
    const principal = (paciente.convenios ?? []).find((c) => c.papel === 'principal');
    return principal?.convenio?.nome ?? '—';
}

// ─── Anonimizar ───────────────────────────────────────────────────────────────

const anonimizarModal = reactive({
    open: false,
    pacienteId: null,
    loading: false,
    error: null,
});

function openAnonimizarModal(paciente) {
    anonimizarModal.open = true;
    anonimizarModal.pacienteId = paciente.id;
    anonimizarModal.loading = false;
    anonimizarModal.error = null;
}

function closeAnonimizarModal() {
    anonimizarModal.open = false;
}

async function confirmAnonimizar() {
    anonimizarModal.loading = true;
    anonimizarModal.error = null;
    try {
        await api.post(`/pacientes/${anonimizarModal.pacienteId}/anonimizar`);
        closeAnonimizarModal();
        refetch();
    } catch (err) {
        const status = err.response?.status;
        if (status === 403) {
            anonimizarModal.error = t('common.error_generic');
        } else {
            anonimizarModal.error = t('common.error_generic');
        }
        anonimizarModal.loading = false;
    }
}

// ─── Exportar CSV ─────────────────────────────────────────────────────────────

function exportarCsv() {
    const params = new URLSearchParams();
    if (filters.value.q) { params.append('q', filters.value.q); }
    if (filters.value.status) { params.append('status', filters.value.status); }
    if (filters.value.origem) { params.append('origem', filters.value.origem); }
    if (filters.value.profissional_responsavel_id) {
        params.append('profissional_responsavel_id', filters.value.profissional_responsavel_id);
    }

    const baseUrl = (api.defaults.baseURL ?? '/api/v1').replace(/\/$/, '');
    window.location.href = `${baseUrl}/pacientes/exportar?${params.toString()}`;
}

// ─── Dropdown de ações ────────────────────────────────────────────────────────

const openDropdown = ref(null);

function toggleDropdown(id) {
    openDropdown.value = openDropdown.value === id ? null : id;
}

function closeDropdown() {
    openDropdown.value = null;
}
</script>

<template>
    <main class="min-h-screen bg-surface py-8 px-4" @click="closeDropdown">
        <div class="mx-auto max-w-7xl">

            <!-- ── Cabeçalho ──────────────────────────────────────────────────── -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-2xl font-bold text-foreground">
                    {{ t('paciente.list.title') }}
                </h1>
                <div class="flex gap-2">
                    <!-- Exportar CSV (Admin Clínica only) -->
                    <button
                        v-if="auth.hasPermission('paciente.export')"
                        type="button"
                        class="inline-flex items-center rounded-lg border border-border px-4 py-2.5 text-sm font-semibold text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="exportarCsv"
                    >
                        {{ t('import.export_cta') ?? 'Exportar CSV' }}
                    </button>
                    <!-- Importar (Admin Clínica only) -->
                    <button
                        v-if="auth.hasPermission('paciente.import')"
                        type="button"
                        class="inline-flex items-center rounded-lg border border-primary-300 bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="router.push('/panel/pacientes/importar')"
                    >
                        {{ t('import.upload_cta') }}
                    </button>
                    <button
                        v-if="auth.hasPermission('paciente.create')"
                        type="button"
                        class="inline-flex items-center rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="router.push('/panel/pacientes/novo')"
                    >
                        + {{ t('paciente.list.new_cta') }}
                    </button>
                </div>
            </div>

            <!-- ── Toolbar de filtros (sticky) ────────────────────────────────── -->
            <div class="sticky top-0 z-10 mb-4 rounded-xl border border-border bg-surface-elevated p-4 shadow-[var(--shadow-card)]">
                <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end">

                    <!-- Busca -->
                    <div class="flex flex-col gap-1 md:flex-1 md:min-w-48">
                        <label for="filter-q" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('common.search') }}
                        </label>
                        <input
                            id="filter-q"
                            v-model="filters.q"
                            type="search"
                            :placeholder="t('paciente.list.search_placeholder')"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            @input="filters.page = 1"
                        />
                    </div>

                    <!-- Status -->
                    <div class="flex flex-col gap-1">
                        <label for="filter-status" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('paciente.list.filters.status') }}
                        </label>
                        <select
                            id="filter-status"
                            v-model="filters.status"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            @change="filters.page = 1"
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

                    <!-- Origem -->
                    <div class="flex flex-col gap-1">
                        <label for="filter-origem" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('paciente.list.filters.origem') }}
                        </label>
                        <select
                            id="filter-origem"
                            v-model="filters.origem"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            @change="filters.page = 1"
                        >
                            <option
                                v-for="opt in ORIGEM_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Profissional -->
                    <div class="flex flex-col gap-1">
                        <label for="filter-prof" class="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                            {{ t('paciente.list.filters.profissional') }}
                        </label>
                        <select
                            id="filter-prof"
                            v-model="filters.profissional_responsavel_id"
                            class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                            @change="filters.page = 1"
                        >
                            <option value="">
                                {{ t('paciente.list.filters.profissional') }}: Todos
                            </option>
                            <option
                                v-for="prof in profissionais"
                                :key="prof.id"
                                :value="prof.id"
                            >
                                {{ prof.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Botão limpar -->
                    <button
                        type="button"
                        class="rounded-lg border border-border px-3 py-2 text-sm font-medium text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="clearFilters"
                    >
                        {{ t('audit.list.filter_clear') }}
                    </button>
                </div>

                <!-- Tags multi-select -->
                <div
                    v-if="tagsDisponiveis.length > 0"
                    class="mt-3 flex flex-wrap gap-1.5"
                    role="group"
                    :aria-label="t('paciente.list.filters.tag')"
                >
                    <span class="self-center text-xs font-medium uppercase tracking-wide text-foreground-muted mr-1">
                        {{ t('paciente.list.filters.tag') }}:
                    </span>
                    <button
                        v-for="tag in tagsDisponiveis"
                        :key="tag.id"
                        type="button"
                        :aria-pressed="filters['tag[]'].includes(tag.id)"
                        class="rounded-full border px-2.5 py-0.5 text-xs font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="filters['tag[]'].includes(tag.id)
                            ? 'border-primary-500 bg-primary-50 text-primary-700'
                            : 'border-border bg-surface text-foreground-muted hover:border-primary-400'"
                        @click.stop="toggleTag(tag.id)"
                    >
                        {{ tag.nome ?? tag.name }}
                    </button>
                </div>
            </div>

            <!-- ── Loading ────────────────────────────────────────────────────── -->
            <div
                v-if="loading"
                class="flex items-center justify-center py-16"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="text-sm text-foreground-muted">{{ t('common.loading') }}</span>
            </div>

            <!-- ── Erro ───────────────────────────────────────────────────────── -->
            <div
                v-else-if="error"
                role="alert"
                aria-live="assertive"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600"
            >
                {{ error }}
            </div>

            <!-- ── Conteúdo ────────────────────────────────────────────────────── -->
            <template v-else>

                <!-- Empty state -->
                <div
                    v-if="pacientes.length === 0"
                    class="flex flex-col items-center justify-center gap-4 py-20 text-center"
                >
                    <p class="text-foreground-muted text-sm">
                        Nenhum paciente encontrado para os filtros aplicados.
                    </p>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        @click="clearFilters"
                    >
                        Limpar filtros
                    </button>
                </div>

                <template v-else>

                    <!-- ── Tabela (desktop) ────────────────────────────────────── -->
                    <div class="hidden md:block overflow-x-auto rounded-xl border border-border bg-surface-elevated shadow-[var(--shadow-card)]">
                        <table class="w-full text-sm">
                            <thead class="border-b border-border bg-surface text-left">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">Nome</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">Status</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">{{ t('paciente.form.telefone_primario') }}</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">{{ t('paciente.list.filters.convenio') }}</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">{{ t('paciente.list.filters.profissional') }}</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">Última atualização</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted text-right">{{ t('users.list.column_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="paciente in pacientes"
                                    :key="paciente.id"
                                    class="cursor-pointer transition hover:bg-surface"
                                    @click="router.push(`/panel/pacientes/${paciente.id}`)"
                                >
                                    <td class="px-4 py-3 font-medium text-foreground">
                                        {{ paciente.nome }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="statusClass(paciente.status)"
                                        >
                                            {{ statusLabel(paciente.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-foreground-muted text-xs">
                                        {{ paciente.telefone_primario ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-foreground-muted text-xs">
                                        {{ convenioLabel(paciente) }}
                                    </td>
                                    <td class="px-4 py-3 text-foreground-muted text-xs">
                                        {{ paciente.profissional_responsavel?.nome ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-foreground-muted text-xs">
                                        {{ paciente.updated_at ? formatDateTime(paciente.updated_at) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right" @click.stop>
                                        <div class="relative inline-block">
                                            <button
                                                type="button"
                                                :aria-label="`Ações para ${paciente.nome}`"
                                                class="rounded px-2 py-1 text-xs font-medium text-foreground-muted transition hover:bg-surface hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                                @click="toggleDropdown(paciente.id)"
                                            >
                                                ⋯
                                            </button>
                                            <div
                                                v-if="openDropdown === paciente.id"
                                                class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-border bg-surface-elevated shadow-xl"
                                                role="menu"
                                            >
                                                <button
                                                    type="button"
                                                    role="menuitem"
                                                    class="flex w-full items-center px-3 py-2 text-sm text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                                    @click="router.push(`/panel/pacientes/${paciente.id}`)"
                                                >
                                                    Ver
                                                </button>
                                                <button
                                                    v-if="auth.hasPermission('paciente.update')"
                                                    type="button"
                                                    role="menuitem"
                                                    class="flex w-full items-center px-3 py-2 text-sm text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                                    @click="router.push(`/panel/pacientes/${paciente.id}/editar`)"
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    v-if="auth.hasRole('admin-clinica')"
                                                    type="button"
                                                    role="menuitem"
                                                    class="flex w-full items-center px-3 py-2 text-sm text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                                    @click="openAnonimizarModal(paciente)"
                                                >
                                                    {{ t('paciente.show.anonimizar_cta') }}
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ── Cards (mobile) ──────────────────────────────────────── -->
                    <div class="md:hidden space-y-3">
                        <div
                            v-for="paciente in pacientes"
                            :key="paciente.id"
                            class="cursor-pointer rounded-xl border border-border bg-surface-elevated p-4 shadow-[var(--shadow-card)] transition hover:border-primary-400"
                            @click="router.push(`/panel/pacientes/${paciente.id}`)"
                        >
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <p class="font-medium text-foreground text-sm">{{ paciente.nome }}</p>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium shrink-0"
                                    :class="statusClass(paciente.status)"
                                >
                                    {{ statusLabel(paciente.status) }}
                                </span>
                            </div>
                            <p class="text-xs text-foreground-muted">
                                {{ paciente.telefone_primario ?? '—' }}
                            </p>
                            <p class="mt-1 text-xs text-foreground-muted">
                                {{ convenioLabel(paciente) }}
                            </p>
                            <div class="mt-3 flex gap-2" @click.stop>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="router.push(`/panel/pacientes/${paciente.id}`)"
                                >
                                    Ver
                                </button>
                                <button
                                    v-if="auth.hasPermission('paciente.update')"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="router.push(`/panel/pacientes/${paciente.id}/editar`)"
                                >
                                    Editar
                                </button>
                                <button
                                    v-if="auth.hasRole('admin-clinica')"
                                    type="button"
                                    class="rounded-lg border border-danger-300 px-3 py-1.5 text-xs font-medium text-danger-600 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                    @click="openAnonimizarModal(paciente)"
                                >
                                    {{ t('paciente.show.anonimizar_cta') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Paginação ────────────────────────────────────────────── -->
                    <div
                        v-if="totalPages > 1"
                        class="mt-4 flex items-center justify-between"
                        aria-label="Paginação"
                    >
                        <p class="text-xs text-foreground-muted">
                            Página {{ currentPage }} de {{ totalPages }}
                            <span v-if="meta.total">({{ meta.total }} pacientes)</span>
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="currentPage <= 1"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="goToPage(currentPage - 1)"
                            >
                                {{ t('common.back') }}
                            </button>
                            <button
                                type="button"
                                :disabled="currentPage >= totalPages"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="goToPage(currentPage + 1)"
                            >
                                {{ t('common.next') }}
                            </button>
                        </div>
                    </div>
                </template>
            </template>
        </div>

        <!-- ─── Modal Anonimizar ──────────────────────────────────────────────── -->
        <ConfirmModal
            :open="anonimizarModal.open"
            :title="t('paciente.show.anonimizar_cta')"
            @close="closeAnonimizarModal"
            @confirm="confirmAnonimizar"
        >
            <p class="text-sm text-foreground-muted">
                {{ t('paciente.show.anonimizar_confirm') }}
            </p>
            <div
                v-if="anonimizarModal.error"
                role="alert"
                aria-live="assertive"
                class="mt-3 rounded-lg border border-danger-500 bg-danger-50 px-3 py-2 text-sm text-danger-600"
            >
                {{ anonimizarModal.error }}
            </div>
            <template #actions>
                <button
                    type="button"
                    :disabled="anonimizarModal.loading"
                    class="rounded-lg bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="confirmAnonimizar"
                >
                    {{ anonimizarModal.loading ? t('common.loading') : t('common.confirm') }}
                </button>
            </template>
        </ConfirmModal>
    </main>
</template>

<script setup>
/**
 * T164 / T165 (Fase 7 Lote E) — Página de Relatório de Receitas (US-8.4).
 *
 * Features:
 *  - Filtros reativos via PrescriptionReportFilters (debounce 300ms).
 *  - Tabela paginada cursor-based com skeleton, empty state, error + retry.
 *  - Coluna criticidade (bolinha verde/amarelo/vermelho — campo do backend).
 *  - Coluna is_renewed (badge "Renovada").
 *  - Receitas mascaradas: placeholder "•••" + tooltip "Acesso restrito".
 *  - Botão "Exportar CSV": modal a11y de confirmação quando tipo inclui controladas.
 *  - Paginação: botão "Carregar mais" (cursor).
 *  - Reverb (T165): subscribe canal prescriptions.{tenantId}, event PrescriptionsReportRefresh → re-fetch.
 *
 * A11y: WCAG AA — foco visível, labels, aria-live em alertas, modal a11y Fase 6.
 */
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { DateTime } from 'luxon';
import { usePrescriptionsStore } from '@/stores/prescriptionsStore';
import { useAuthStore } from '@/stores/auth';
import api from '@/lib/api';
import PrescriptionTypeBadge from '@/components/prescriptions/PrescriptionTypeBadge.vue';
import PrescriptionStatusPill from '@/components/prescriptions/PrescriptionStatusPill.vue';
import ControlledMaskingBanner from '@/components/prescriptions/ControlledMaskingBanner.vue';
import PrescriptionReportFilters from '@/components/prescriptions/PrescriptionReportFilters.vue';

const router = useRouter();
const store = usePrescriptionsStore();
const auth = useAuthStore();

// ─── Filtros ──────────────────────────────────────────────────────────────────

const filters = ref({
    status: null,
    type: null,
    professional_id: null,
    patient_id: null,
    expires_after: null,
    expires_before: null,
});

// ─── Profissionais (para o select do filtro) ──────────────────────────────────

const professionals = ref([]);

async function loadProfessionals() {
    try {
        const { data } = await api.get('/agenda/professionals', { params: { per_page: 100 } });
        professionals.value = data.data ?? [];
    } catch {
        // Não crítico — select ficará sem opções
    }
}

// ─── Carregamento / paginação ─────────────────────────────────────────────────

async function load(cursor = null, append = false) {
    const params = { ...filters.value };
    if (cursor) params.cursor = cursor;
    await store.fetchReport(params, append);
}

async function loadMore() {
    const cursor = store.report.pagination.next_cursor;
    if (!cursor) return;
    await load(cursor, true);
}

// ─── Watch filtros → refetch ──────────────────────────────────────────────────

watch(
    filters,
    () => {
        load();
    },
    { deep: true },
);

// ─── Toast local ─────────────────────────────────────────────────────────────

const toast = ref(null);
let toastTimer = null;

function showToast(message, type = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { message, type };
    toastTimer = setTimeout(() => {
        toast.value = null;
    }, 5000);
}

// ─── Export CSV — modal de confirmação ───────────────────────────────────────

const showExportModal = ref(false);
const exportModalEl = ref(null);
const exportConfirmBtnRef = ref(null);
const exporting = ref(false);

/** Verifica se o filtro atual pode retornar controladas. */
const mayHaveControlled = computed(() => {
    return !filters.value.type || filters.value.type === 'controlled';
});

function openExportModal() {
    if (mayHaveControlled.value) {
        showExportModal.value = true;
        nextTick(() => exportConfirmBtnRef.value?.focus());
    } else {
        // Sem risco de controladas — exporta direto
        doExport();
    }
}

function closeExportModal() {
    showExportModal.value = false;
}

function handleExportOverlayClick(e) {
    if (e.target === e.currentTarget) closeExportModal();
}

function trapExportFocus(e) {
    const focusable = exportModalEl.value
        ? Array.from(
              exportModalEl.value.querySelectorAll(
                  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
              ),
          )
        : [];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.key === 'Tab') {
        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }
}

async function doExport() {
    closeExportModal();
    exporting.value = true;
    try {
        const result = await store.exportReport({ ...filters.value });
        const msg = result.hasControlled
            ? `CSV exportado: ${result.filename} (contém receitas controladas — CONFIDENCIAL)`
            : `CSV exportado: ${result.filename}`;
        showToast(msg);
    } catch {
        showToast('Não foi possível exportar o relatório. Tente novamente.', 'error');
    } finally {
        exporting.value = false;
    }
}

// ─── Criticidade (campo vindo do backend já calculado) ────────────────────────

const CRITICALITY_LABEL = {
    green: 'Válida',
    yellow: 'Vence em breve',
    red: 'Crítica ou vencida',
};

const CRITICALITY_DOT_CLASSES = {
    green: 'text-success-600',
    yellow: 'text-warning-600',
    red: 'text-danger-600',
};

// ─── Formatação pt-BR ─────────────────────────────────────────────────────────

function fmtDate(iso) {
    if (!iso) return '—';
    return DateTime.fromISO(iso).toFormat('dd/MM/yyyy');
}

function fmtRelative(iso) {
    if (!iso) return '';
    return DateTime.fromISO(iso).toRelative({ locale: 'pt-BR' });
}

// ─── Reverb — subscribe canal prescriptions.{tenantId} (T165) ────────────────

function subscribeReportRefresh() {
    store.subscribeToReportRefresh(auth.currentTenantId);

    // Listener adicional específico da página: re-fetch lista ao receber evento
    const echo = window.Echo;
    const tenantId = auth.currentTenantId;
    if (!echo || !tenantId) return;

    try {
        echo.private(`prescriptions.${tenantId}`).listen(
            '.PrescriptionsReportRefresh',
            async () => {
                // Re-fetch mantendo filtros atuais, sem cursor (volta ao início)
                await load();
            },
        );
    } catch {
        // Echo não disponível — silencioso
    }
}

function unsubscribeReportRefresh() {
    store.unsubscribeFromReportRefresh(auth.currentTenantId);
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(() => {
    load();
    loadProfessionals();
    subscribeReportRefresh();
});

onUnmounted(() => {
    unsubscribeReportRefresh();
    if (toastTimer) clearTimeout(toastTimer);
});

// ─── Computed aliases para legibilidade ──────────────────────────────────────

const reportList = computed(() => store.report.list);
const reportLoading = computed(() => store.report.loading);
const reportError = computed(() => store.report.error);
const reportPagination = computed(() => store.report.pagination);
const hasMore = computed(() => !!store.report.pagination.next_cursor);
const hasMasked = computed(() => reportList.value.some((p) => p.masked));
</script>

<template>
    <div class="min-h-screen bg-background">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav
                class="mb-2 flex items-center gap-1 text-xs text-foreground-muted"
                aria-label="Breadcrumb"
            >
                <router-link
                    :to="{ name: 'prescriptions.index' }"
                    class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
                >
                    Receituários
                </router-link>
                <svg
                    class="h-3 w-3 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M8.25 4.5l7.5 7.5-7.5 7.5"
                    />
                </svg>
                <span class="text-foreground" aria-current="page">Relatório</span>
            </nav>

            <!-- Cabeçalho -->
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Relatório de Receitas</h1>
                    <p class="text-sm text-foreground-muted mt-0.5">
                        Visão consolidada das receitas com filtros e exportação CSV.
                    </p>
                </div>

                <!-- Botão Exportar CSV -->
                <button
                    type="button"
                    :disabled="exporting || reportLoading"
                    class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 transition"
                    @click="openExportModal"
                >
                    <svg
                        v-if="exporting"
                        class="h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"
                        />
                    </svg>
                    {{ exporting ? 'Exportando...' : 'Exportar CSV' }}
                </button>
            </div>

            <!-- Bloco de filtros -->
            <div class="mb-4 rounded-xl border border-border bg-surface p-4">
                <PrescriptionReportFilters
                    v-model="filters"
                    :professionals="professionals"
                    @clear="load()"
                />
            </div>

            <!-- Loading skeleton -->
            <div
                v-if="reportLoading && reportList.length === 0"
                aria-busy="true"
                aria-label="Carregando relatório"
                class="space-y-2"
            >
                <div
                    v-for="i in 6"
                    :key="i"
                    class="h-14 rounded-xl bg-surface-elevated animate-pulse"
                />
            </div>

            <!-- Error state -->
            <div
                v-else-if="reportError && reportList.length === 0"
                class="rounded-xl border border-border bg-surface p-8 text-center"
            >
                <p class="text-sm text-danger-600 mb-3" role="alert">{{ reportError }}</p>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="load()"
                >
                    Tentar novamente
                </button>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="!reportLoading && reportList.length === 0"
                class="rounded-xl border border-dashed border-border p-12 text-center"
            >
                <svg
                    class="mx-auto h-10 w-10 text-foreground-muted mb-3"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"
                    />
                </svg>
                <p class="text-sm text-foreground-muted">
                    Nenhuma receita encontrada com os filtros selecionados.
                </p>
            </div>

            <!-- Tabela -->
            <div v-else class="overflow-hidden rounded-xl border border-border bg-surface">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-surface-elevated">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide"
                                >
                                    Paciente
                                </th>
                                <th
                                    scope="col"
                                    class="hidden px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide sm:table-cell"
                                >
                                    Profissional
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide"
                                >
                                    Tipo
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="hidden px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide md:table-cell"
                                >
                                    Validade
                                </th>
                                <th
                                    scope="col"
                                    class="hidden px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide lg:table-cell"
                                >
                                    Criticidade
                                </th>
                                <th
                                    scope="col"
                                    class="hidden px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide lg:table-cell"
                                >
                                    Renovada
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold text-foreground-muted uppercase tracking-wide"
                                >
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="prescription in reportList"
                                :key="prescription.id"
                                class="hover:bg-surface-elevated transition"
                            >
                                <!-- Paciente -->
                                <td class="px-4 py-3 font-medium text-foreground">
                                    <span
                                        v-if="prescription.masked"
                                        class="text-foreground-muted tracking-widest"
                                        role="img"
                                        :title="
                                            $t
                                                ? $t('prescription.restricted_access')
                                                : 'Acesso restrito'
                                        "
                                        aria-label="Conteúdo restrito"
                                    >
                                        •••
                                    </span>
                                    <span v-else>
                                        {{
                                            prescription.patient_name ??
                                            `Paciente #${prescription.patient_id}`
                                        }}
                                    </span>
                                </td>

                                <!-- Profissional -->
                                <td class="hidden px-4 py-3 text-foreground-muted sm:table-cell">
                                    <span
                                        v-if="prescription.masked"
                                        class="tracking-widest"
                                        :title="
                                            $t
                                                ? $t('prescription.restricted_access')
                                                : 'Acesso restrito'
                                        "
                                        >•••</span
                                    >
                                    <span v-else>{{ prescription.professional_name ?? '—' }}</span>
                                </td>

                                <!-- Tipo -->
                                <td class="px-4 py-3">
                                    <span
                                        v-if="prescription.masked"
                                        class="tracking-widest text-foreground-muted"
                                        :title="
                                            $t
                                                ? $t('prescription.restricted_access')
                                                : 'Acesso restrito'
                                        "
                                        >•••</span
                                    >
                                    <PrescriptionTypeBadge v-else :type="prescription.type" />
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3">
                                    <span
                                        v-if="prescription.masked"
                                        class="tracking-widest text-foreground-muted"
                                        :title="
                                            $t
                                                ? $t('prescription.restricted_access')
                                                : 'Acesso restrito'
                                        "
                                        >•••</span
                                    >
                                    <PrescriptionStatusPill v-else :status="prescription.status" />
                                </td>

                                <!-- Validade -->
                                <td class="hidden px-4 py-3 text-foreground-muted md:table-cell">
                                    <span
                                        v-if="prescription.masked"
                                        class="tracking-widest"
                                        :title="
                                            $t
                                                ? $t('prescription.restricted_access')
                                                : 'Acesso restrito'
                                        "
                                        >•••</span
                                    >
                                    <span v-else>
                                        {{ fmtDate(prescription.expires_at) }}
                                        <span class="block text-xs">{{
                                            fmtRelative(prescription.expires_at)
                                        }}</span>
                                    </span>
                                </td>

                                <!-- Criticidade -->
                                <td class="hidden px-4 py-3 lg:table-cell">
                                    <span
                                        v-if="prescription.criticality"
                                        class="inline-flex items-center gap-1 text-xs font-medium"
                                        :class="CRITICALITY_DOT_CLASSES[prescription.criticality]"
                                        :title="CRITICALITY_LABEL[prescription.criticality]"
                                    >
                                        <svg
                                            class="h-3 w-3"
                                            viewBox="0 0 8 8"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <circle cx="4" cy="4" r="4" />
                                        </svg>
                                        {{ CRITICALITY_LABEL[prescription.criticality] }}
                                    </span>
                                    <span v-else class="text-xs text-foreground-muted">—</span>
                                </td>

                                <!-- Renovada -->
                                <td class="hidden px-4 py-3 lg:table-cell">
                                    <span
                                        v-if="prescription.is_renewed"
                                        class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-primary-200"
                                    >
                                        Renovada
                                    </span>
                                    <span v-else class="text-xs text-foreground-muted">—</span>
                                </td>

                                <!-- Ações -->
                                <td class="px-4 py-3 text-right">
                                    <div
                                        v-if="prescription.masked"
                                        class="text-xs text-foreground-muted italic"
                                    >
                                        Restrito
                                    </div>
                                    <div v-else class="flex items-center justify-end gap-2">
                                        <router-link
                                            :to="{
                                                name: 'prescriptions.show',
                                                params: { id: prescription.id },
                                            }"
                                            class="rounded px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                                        >
                                            Ver
                                        </router-link>

                                        <router-link
                                            v-if="
                                                prescription.status === 'active' &&
                                                !prescription.is_renewed
                                            "
                                            :to="{
                                                name: 'prescriptions.renew',
                                                params: { id: prescription.id },
                                            }"
                                            class="rounded px-2 py-1 text-xs font-medium text-foreground-muted hover:text-foreground hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                                        >
                                            Renovar
                                        </router-link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Banner mascaramento -->
                <div v-if="hasMasked" class="border-t border-border px-4 py-3">
                    <ControlledMaskingBanner :masked="true" />
                </div>

                <!-- Paginação cursor -->
                <div v-if="hasMore" class="border-t border-border px-4 py-3 text-center">
                    <button
                        type="button"
                        :disabled="reportLoading"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 transition"
                        @click="loadMore"
                    >
                        {{ reportLoading ? 'Carregando...' : 'Carregar mais' }}
                    </button>
                </div>
            </div>

            <!-- Toast -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="toast"
                    role="alert"
                    aria-live="assertive"
                    aria-atomic="true"
                    class="fixed bottom-4 right-4 z-50 flex items-center gap-2 rounded-lg px-4 py-3 text-sm shadow-lg max-w-sm"
                    :class="[
                        toast.type === 'error'
                            ? 'bg-danger-50 text-danger-700 ring-1 ring-danger-200'
                            : 'bg-success-50 text-success-700 ring-1 ring-success-200',
                    ]"
                >
                    {{ toast.message }}
                </div>
            </Transition>
        </div>

        <!-- Modal confirmação de export (a11y Fase 6) -->
        <Teleport to="body">
            <div
                v-if="showExportModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                @click="handleExportOverlayClick"
                @keydown.esc.prevent="closeExportModal"
                @keydown="trapExportFocus"
            >
                <div
                    ref="exportModalEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="export-modal-title"
                    class="w-full rounded-t-2xl bg-surface shadow-xl sm:max-w-md sm:rounded-xl"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-border px-6 py-4">
                        <h2 id="export-modal-title" class="text-base font-semibold text-foreground">
                            Exportar relatório CSV
                        </h2>
                        <button
                            type="button"
                            class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            aria-label="Fechar modal"
                            @click="closeExportModal"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Corpo -->
                    <div class="px-6 py-4 space-y-4">
                        <!-- Aviso CONFIDENCIAL -->
                        <div
                            class="flex items-start gap-3 rounded-lg bg-warning-50 border border-warning-200 px-4 py-3"
                        >
                            <svg
                                class="mt-0.5 h-4 w-4 shrink-0 text-warning-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                                />
                            </svg>
                            <p class="text-sm text-warning-800">
                                Este export <strong>pode conter receitas controladas</strong>. O
                                arquivo terá o prefixo
                                <code class="rounded bg-warning-100 px-1 text-xs font-mono"
                                    >CONFIDENCIAL_</code
                                >
                                e o acesso será auditado conforme LGPD.
                            </p>
                        </div>

                        <p class="text-sm text-foreground">Deseja continuar com a exportação?</p>

                        <!-- Ações -->
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                                @click="closeExportModal"
                            >
                                Cancelar
                            </button>
                            <button
                                ref="exportConfirmBtnRef"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                                @click="doExport"
                            >
                                Confirmar exportação
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

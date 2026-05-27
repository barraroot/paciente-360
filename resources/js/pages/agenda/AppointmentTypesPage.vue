<script setup>
/**
 * T058 — Página CRUD tipos de atendimento (US-6.2).
 *
 * Refinamentos UX (auditoria 2026-05-13 — Lote A):
 *  - Substituído confirm() nativo por modal de confirmação acessível (P0)
 *  - Skeleton com aria-busy + error retry em pt-BR (P0)
 *  - Empty state com CTA "Criar primeiro tipo" (P1)
 *  - Toast feedback create/update/inativar (P1)
 *  - Formatação R$ via Intl.NumberFormat pt-BR (P0)
 *  - Tabela → cards em mobile < 768px (P1)
 *  - Modal de form com focus trap + Esc fecha (P1)
 *  - Tooltip explicativo "Tipo: Retorno (categoria de consulta)" (clarify nº 4) (P2)
 *  - Erros 422 mapeados por campo em fieldErrors (P0)
 */
import { ref, onMounted, nextTick } from 'vue';
import {
    listAppointmentTypes,
    createAppointmentType,
    updateAppointmentType,
    deleteAppointmentType,
} from '@/lib/agendaApi';
import AppointmentTypeForm from '@/components/agenda/AppointmentTypeForm.vue';

// ─── Estado principal ─────────────────────────────────────────────────────────

const types = ref([]);
const includeInactive = ref(false);
const loading = ref(false);
const error = ref(null);

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref(null);
let toastTimer = null;

function showToast(message, type = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { message, type };
    toastTimer = setTimeout(() => {
        toast.value = null;
    }, 5000);
}

// ─── Carregamento ─────────────────────────────────────────────────────────────

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await listAppointmentTypes({ includeInactive: includeInactive.value });
        types.value = res.data.data;
    } catch {
        error.value =
            'Não foi possível carregar os tipos de atendimento. Verifique sua conexão e tente novamente.';
        types.value = [];
    } finally {
        loading.value = false;
    }
}

// ─── Modal de formulário ──────────────────────────────────────────────────────

const showFormModal = ref(false);
const editing = ref(null);
const formDialogEl = ref(null);
const formRef = ref(null);

const defaultForm = () => ({
    nome: '',
    duration_minutes: 30,
    buffer_minutes: 5,
    valor_particular: 0,
    valor_convenio_default: null,
    min_cancellation_hours: null,
    cor: '#3B82F6',
    descricao: '',
    intent_ia: '',
});

function openCreate() {
    editing.value = null;
    showFormModal.value = true;
    nextTick(() => {
        formDialogEl.value?.querySelector('input, select, textarea')?.focus();
    });
}

function openEdit(type) {
    editing.value = { ...type };
    showFormModal.value = true;
    nextTick(() => {
        formDialogEl.value?.querySelector('input, select, textarea')?.focus();
    });
}

function closeFormModal() {
    showFormModal.value = false;
    editing.value = null;
}

function trapFocus(el, e) {
    if (!el) return;
    const focusable = Array.from(
        el.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
    );
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

async function handleFormSubmit({ data, fieldErrors }) {
    try {
        if (editing.value?.id) {
            await updateAppointmentType(editing.value.id, data);
            showToast('Tipo de atendimento atualizado com sucesso.');
        } else {
            await createAppointmentType(data);
            showToast('Tipo de atendimento criado com sucesso.');
        }
        closeFormModal();
        await load();
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            // Repassa os erros para o componente filho via callback
            fieldErrors(e.response.data.errors);
        } else {
            showToast(
                e?.response?.data?.message ?? 'Não foi possível salvar. Tente novamente.',
                'error',
            );
        }
    }
}

// ─── Modal de confirmação de inativação ──────────────────────────────────────

const showInactivateModal = ref(false);
const inactivateTarget = ref(null);
const inactivating = ref(false);
const inactivateDialogEl = ref(null);

function openInactivate(type) {
    inactivateTarget.value = type;
    showInactivateModal.value = true;
    nextTick(() => {
        inactivateDialogEl.value?.querySelector('button[data-autofocus]')?.focus();
    });
}

function closeInactivateModal() {
    showInactivateModal.value = false;
    inactivateTarget.value = null;
}

async function confirmInactivate() {
    if (!inactivateTarget.value || inactivating.value) return;
    inactivating.value = true;
    try {
        await deleteAppointmentType(inactivateTarget.value.id);
        const nome = inactivateTarget.value.nome;
        closeInactivateModal();
        showToast(`"${nome}" inativado. O histórico de consultas está preservado.`);
        await load();
    } catch {
        showToast('Não foi possível inativar o tipo. Tente novamente.', 'error');
    } finally {
        inactivating.value = false;
    }
}

// ─── Formatação ──────────────────────────────────────────────────────────────

function formatCurrency(value) {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

// ─── Init ─────────────────────────────────────────────────────────────────────

onMounted(load);
</script>

<template>
    <div class="p-4 sm:p-6 space-y-6">
        <!-- Cabeçalho -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="text-xl font-semibold text-foreground">Tipos de Atendimento</h1>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition disabled:opacity-50"
                :disabled="loading"
                @click="openCreate"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                Novo tipo
            </button>
        </div>

        <!-- Filtro inativos -->
        <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
            <input
                v-model="includeInactive"
                type="checkbox"
                class="h-4 w-4 rounded border-border accent-primary-700"
                @change="load"
            />
            Incluir tipos inativos
        </label>

        <!-- Toast -->
        <div
            v-if="toast"
            role="alert"
            aria-live="assertive"
            class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg transition"
            :class="
                toast.type === 'success' ? 'bg-success-500 text-white' : 'bg-danger-500 text-white'
            "
        >
            <svg
                v-if="toast.type === 'success'"
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                />
            </svg>
            <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            {{ toast.message }}
        </div>

        <!-- Loading skeleton -->
        <div
            v-if="loading"
            aria-busy="true"
            aria-label="Carregando tipos de atendimento"
            class="space-y-2"
        >
            <div v-for="i in 4" :key="i" class="h-12 animate-pulse rounded-lg bg-surface-muted" />
        </div>

        <!-- Error state -->
        <div
            v-else-if="error"
            class="rounded-lg border border-danger-500/30 bg-danger-50 p-4 flex flex-col sm:flex-row sm:items-center gap-3"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5 shrink-0 text-danger-600"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <p class="text-sm text-danger-600 flex-1">{{ error }}</p>
            <button
                type="button"
                class="shrink-0 rounded-lg border border-danger-500 px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
                @click="load"
            >
                Tentar novamente
            </button>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="!loading && types.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-12 text-center"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-12 w-12 text-foreground-subtle mb-3"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                />
            </svg>
            <h2 class="text-base font-semibold text-foreground mb-1">
                Nenhum tipo de atendimento cadastrado
            </h2>
            <p class="text-sm text-foreground-muted mb-4 max-w-xs">
                Crie o primeiro tipo para que os profissionais possam configurar suas agendas.
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                @click="openCreate"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                Criar primeiro tipo
            </button>
        </div>

        <!-- Tabela desktop + Cards mobile -->
        <template v-else>
            <!-- Tabela (≥ 768px) -->
            <div class="hidden md:block overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted sticky top-0">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-foreground-muted">
                                Nome
                            </th>
                            <th class="px-4 py-3 text-right font-medium text-foreground-muted">
                                Duração
                            </th>
                            <th class="px-4 py-3 text-right font-medium text-foreground-muted">
                                Valor particular
                            </th>
                            <th class="px-4 py-3 text-center font-medium text-foreground-muted">
                                Situação
                            </th>
                            <th
                                class="px-4 py-3 text-right font-medium text-foreground-muted sr-only"
                            >
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="t in types"
                            :key="t.id"
                            class="hover:bg-surface-subtle transition"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-3 w-3 rounded-full shrink-0"
                                        :style="{ backgroundColor: t.cor }"
                                        aria-hidden="true"
                                    />
                                    <span class="font-medium text-foreground">{{ t.nome }}</span>
                                    <!-- Tooltip "Retorno" (clarify nº 4) -->
                                    <span
                                        v-if="t.nome?.toLowerCase() === 'retorno'"
                                        class="text-xs text-foreground-subtle cursor-help"
                                        title="Categoria de consulta: identifica visitas de acompanhamento (não confundir com cadência automática — Fase 6)"
                                        aria-label="Retorno é uma categoria de consulta: identifica visitas de acompanhamento"
                                        >(categoria)</span
                                    >
                                </div>
                                <p
                                    v-if="t.descricao"
                                    class="mt-0.5 text-xs text-foreground-muted line-clamp-1"
                                >
                                    {{ t.descricao }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">
                                {{ t.duration_minutes }} min
                                <span v-if="t.buffer_minutes" class="text-foreground-muted text-xs">
                                    +{{ t.buffer_minutes }} buf</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">
                                {{ formatCurrency(t.valor_particular) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        t.is_active
                                            ? 'bg-success-50 text-success-700'
                                            : 'bg-surface-muted text-foreground-muted'
                                    "
                                >
                                    {{ t.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        class="text-sm text-primary-700 hover:text-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded transition"
                                        @click="openEdit(t)"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        v-if="t.is_active"
                                        type="button"
                                        class="text-sm text-danger-600 hover:text-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 rounded transition"
                                        @click="openInactivate(t)"
                                    >
                                        Inativar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cards (< 768px) -->
            <div class="md:hidden space-y-3">
                <div
                    v-for="t in types"
                    :key="t.id"
                    class="rounded-lg border border-border bg-surface-elevated p-4 space-y-3 shadow-card"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <span
                                class="h-3 w-3 rounded-full shrink-0 mt-0.5"
                                :style="{ backgroundColor: t.cor }"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="font-semibold text-foreground truncate">{{ t.nome }}</p>
                                <p
                                    v-if="t.descricao"
                                    class="text-xs text-foreground-muted truncate"
                                >
                                    {{ t.descricao }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                t.is_active
                                    ? 'bg-success-50 text-success-700'
                                    : 'bg-surface-muted text-foreground-muted'
                            "
                        >
                            {{ t.is_active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-foreground-muted">Duração</dt>
                            <dd class="font-medium text-foreground">
                                {{ t.duration_minutes }} min
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-foreground-muted">Valor particular</dt>
                            <dd class="font-medium text-foreground">
                                {{ formatCurrency(t.valor_particular) }}
                            </dd>
                        </div>
                    </dl>

                    <div class="flex items-center gap-3 pt-1 border-t border-border">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            @click="openEdit(t)"
                        >
                            Editar
                        </button>
                        <button
                            v-if="t.is_active"
                            type="button"
                            class="flex-1 rounded-lg border border-danger-500/50 px-3 py-2 text-sm font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
                            @click="openInactivate(t)"
                        >
                            Inativar
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ─── Modal de formulário ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showFormModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                @click.self="closeFormModal"
                @keydown.esc.prevent="closeFormModal"
                @keydown="trapFocus(formDialogEl, $event)"
            >
                <div
                    ref="formDialogEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="type-form-modal-title"
                    class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-lg sm:rounded-xl"
                >
                    <div class="flex items-center justify-between border-b border-border px-6 py-4">
                        <h2
                            id="type-form-modal-title"
                            class="text-base font-semibold text-foreground"
                        >
                            {{
                                editing?.id
                                    ? 'Editar tipo de atendimento'
                                    : 'Novo tipo de atendimento'
                            }}
                        </h2>
                        <button
                            type="button"
                            class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            aria-label="Fechar"
                            @click="closeFormModal"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
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

                    <div class="px-6 py-4 overflow-y-auto max-h-[70vh]">
                        <AppointmentTypeForm
                            ref="formRef"
                            :initial-value="editing"
                            @submit="handleFormSubmit"
                            @cancel="closeFormModal"
                        />
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal de confirmação de inativação ─────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showInactivateModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                @click.self="closeInactivateModal"
                @keydown.esc.prevent="closeInactivateModal"
                @keydown="trapFocus(inactivateDialogEl, $event)"
            >
                <div
                    ref="inactivateDialogEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="inactivate-modal-title"
                    class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
                >
                    <div class="px-6 py-4 border-b border-border">
                        <h2
                            id="inactivate-modal-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Inativar tipo de atendimento
                        </h2>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-sm text-foreground">
                            Inativar
                            <strong class="font-semibold">"{{ inactivateTarget?.nome }}"</strong>?
                        </p>
                        <p class="mt-2 text-xs text-foreground-muted">
                            O histórico de consultas é preservado (FR-007). O tipo não estará
                            disponível para novos agendamentos.
                        </p>
                    </div>
                    <div
                        class="flex items-center justify-end gap-3 border-t border-border px-6 py-4"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            @click="closeInactivateModal"
                        >
                            Cancelar
                        </button>
                        <button
                            data-autofocus
                            type="button"
                            :disabled="inactivating"
                            class="rounded-lg bg-danger-500 px-4 py-2 text-sm font-semibold text-white hover:bg-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            @click="confirmInactivate"
                        >
                            <span v-if="inactivating">Inativando...</span>
                            <span v-else>Inativar</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

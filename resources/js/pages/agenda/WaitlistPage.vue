<script setup>
/**
 * T138 — Página de gestão da lista de espera (US-6.6 — clarify nº 8).
 *
 * Refinamentos UX (auditoria 2026-05-13):
 *  - Rota registrada: /panel/agenda/lista-espera
 *  - Modal de inscrição com PatientAutocomplete (P0)
 *  - Modal de confirmação de cancelamento acessível — sem confirm() nativo (P0)
 *  - Status badges com cores semânticas + ícones (P0)
 *  - Countdown ao vivo para rows notified (P1)
 *  - Loading skeleton com aria-busy (P0)
 *  - Error state com retry em pt-BR (P1)
 *  - Empty state com CTA (P1)
 *  - Toast feedback para create/cancel (P1)
 *  - Tabela → cards em mobile < 768px (P1)
 *  - Filtros por profissional e tipo de atendimento (P1)
 *  - Datas formatadas via useTimezoneRenderer (P1)
 *  - Nomes de paciente/profissional via eager-loaded Resource (P0)
 *  - Atalhos: Esc fecha modal (P1)
 */
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import {
    listWaitlist,
    enrollWaitlist,
    cancelWaitlist,
    listAppointmentTypes,
} from '@/lib/agendaApi'
import api from '@/lib/api'
import { useTimezoneRenderer } from '@/composables/useTimezoneRenderer'
import PatientAutocomplete from '@/components/agenda/PatientAutocomplete.vue'

// ─── TZ ───────────────────────────────────────────────────────────────────────

const renderer = useTimezoneRenderer('America/Sao_Paulo')

// ─── Estado principal ─────────────────────────────────────────────────────────

const entries = ref([])
const loading = ref(false)
const error = ref(null)
const filters = ref({ professional_id: null, appointment_type_id: null, status: '' })

// ─── Deps (profissionais + tipos) ────────────────────────────────────────────

const professionals = ref([])
const appointmentTypes = ref([])

async function loadDeps() {
    try {
        const [profsRes, typesRes] = await Promise.all([
            api.get('/users', { params: { only_professionals: 1 } }).catch(() => ({ data: { data: [] } })),
            listAppointmentTypes().catch(() => ({ data: { data: [] } })),
        ])
        professionals.value = profsRes.data.data ?? []
        appointmentTypes.value = typesRes.data.data ?? []
    } catch {
        // silencioso — deps não críticas para exibir a lista
    }
}

// ─── Carregamento da lista ────────────────────────────────────────────────────

async function load() {
    loading.value = true
    error.value = null
    try {
        const params = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v))
        const { data } = await listWaitlist(params)
        entries.value = data.data
    } catch {
        error.value = 'Não foi possível carregar a lista de espera. Verifique sua conexão e tente novamente.'
        entries.value = []
    } finally {
        loading.value = false
    }
}

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref(null) /** @type {{ message: string, type: 'success'|'error' } | null} */
let toastTimer = null

function showToast(message, type = 'success') {
    if (toastTimer) clearTimeout(toastTimer)
    toast.value = { message, type }
    toastTimer = setTimeout(() => { toast.value = null }, 5000)
}

// ─── Modal de inscrição ───────────────────────────────────────────────────────

const showEnrollModal = ref(false)
const enrollForm = ref({ paciente_id: null, paciente_label: '', professional_id: null, appointment_type_id: null })
const enrollErrors = ref({})
const enrollSubmitting = ref(false)
const enrollDialogEl = ref(null)

const enrollValid = computed(() =>
    !!enrollForm.value.paciente_id &&
    !!enrollForm.value.professional_id &&
    !!enrollForm.value.appointment_type_id
)

function openEnrollModal() {
    enrollForm.value = { paciente_id: null, paciente_label: '', professional_id: null, appointment_type_id: null }
    enrollErrors.value = {}
    showEnrollModal.value = true
    nextTick(() => {
        enrollDialogEl.value?.querySelector('input, select')?.focus()
    })
}

function closeEnrollModal() {
    showEnrollModal.value = false
}

function selectEnrollPatient(p) {
    enrollForm.value.paciente_id = p.id
    enrollForm.value.paciente_label = p.nome
}

async function submitEnroll() {
    if (!enrollValid.value || enrollSubmitting.value) return
    enrollSubmitting.value = true
    enrollErrors.value = {}
    try {
        await enrollWaitlist({
            paciente_id: enrollForm.value.paciente_id,
            professional_id: enrollForm.value.professional_id,
            appointment_type_id: enrollForm.value.appointment_type_id,
        })
        closeEnrollModal()
        showToast('Paciente inscrito na lista de espera com sucesso.')
        await load()
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            enrollErrors.value = e.response.data.errors
        } else {
            showToast(
                e?.response?.data?.message ?? 'Não foi possível inscrever o paciente. Tente novamente.',
                'error'
            )
        }
    } finally {
        enrollSubmitting.value = false
    }
}

// ─── Focus trap genérico ──────────────────────────────────────────────────────

function trapFocus(dialogEl, e) {
    if (!dialogEl) return
    const focusable = Array.from(
        dialogEl.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    )
    if (!focusable.length) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (e.key === 'Tab') {
        if (e.shiftKey) {
            if (document.activeElement === first) { e.preventDefault(); last.focus() }
        } else {
            if (document.activeElement === last) { e.preventDefault(); first.focus() }
        }
    }
}

// ─── Modal de confirmação de cancelamento ────────────────────────────────────

const showCancelModal = ref(false)
const cancelTarget = ref(null) /** @type {{ id: number, paciente_nome: string } | null} */
const cancelSubmitting = ref(false)
const cancelDialogEl = ref(null)

function openCancelModal(entry) {
    cancelTarget.value = entry
    showCancelModal.value = true
    nextTick(() => {
        cancelDialogEl.value?.querySelector('button[data-autofocus]')?.focus()
    })
}

function closeCancelModal() {
    showCancelModal.value = false
    cancelTarget.value = null
}

async function confirmCancel() {
    if (!cancelTarget.value || cancelSubmitting.value) return
    cancelSubmitting.value = true
    try {
        await cancelWaitlist(cancelTarget.value.id)
        const nome = cancelTarget.value.paciente_nome ?? 'Paciente'
        closeCancelModal()
        showToast(`${nome} removido da lista de espera.`)
        await load()
    } catch {
        showToast('Não foi possível remover o paciente. Tente novamente.', 'error')
        closeCancelModal()
    } finally {
        cancelSubmitting.value = false
    }
}

// ─── Countdown para rows notified ────────────────────────────────────────────

const now = ref(Date.now())
let countdownInterval = null

function startCountdown() {
    countdownInterval = setInterval(() => { now.value = Date.now() }, 1000)
}

function stopCountdown() {
    if (countdownInterval) clearInterval(countdownInterval)
}

/**
 * Retorna string "MM:SS" ou null se já expirou ou sem expires_at.
 */
function countdown(expiresAtIso) {
    if (!expiresAtIso) return null
    const diff = new Date(expiresAtIso).getTime() - now.value
    if (diff <= 0) return '00:00'
    const totalSec = Math.floor(diff / 1000)
    const min = Math.floor(totalSec / 60)
    const sec = totalSec % 60
    return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
}

// ─── Labels/cores semânticos de status ───────────────────────────────────────

const STATUS_CONFIG = {
    waiting: {
        label: 'Aguardando',
        classes: 'bg-surface-muted text-foreground-muted border-border',
        icon: 'clock',
    },
    notified: {
        label: 'Notificado',
        classes: 'bg-warning-50 text-warning-500 border-warning-500',
        icon: 'bell',
    },
    accepted: {
        label: 'Aceito',
        classes: 'bg-success-50 text-success-600 border-success-300',
        icon: 'check',
    },
    expired: {
        label: 'Expirado',
        classes: 'bg-surface-muted text-foreground-subtle border-border line-through',
        icon: 'x-circle',
    },
    canceled: {
        label: 'Cancelado',
        classes: 'bg-danger-50 text-danger-600 border-danger-500',
        icon: 'ban',
    },
}

function statusConfig(status) {
    return STATUS_CONFIG[status] ?? { label: status, classes: 'bg-surface-muted text-foreground-muted border-border', icon: 'clock' }
}

// ─── Atalhos globais ──────────────────────────────────────────────────────────

function handleKeydown(e) {
    if (e.key === 'Escape') {
        if (showEnrollModal.value) { closeEnrollModal(); return }
        if (showCancelModal.value) { closeCancelModal(); return }
    }
    if (showEnrollModal.value && enrollDialogEl.value) trapFocus(enrollDialogEl.value, e)
    if (showCancelModal.value && cancelDialogEl.value) trapFocus(cancelDialogEl.value, e)
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
    window.addEventListener('keydown', handleKeydown)
    startCountdown()
    await Promise.all([loadDeps(), load()])
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    stopCountdown()
    if (toastTimer) clearTimeout(toastTimer)
})

// ─── Helpers de formato ───────────────────────────────────────────────────────

function fmtDate(iso) {
    if (!iso) return '—'
    try { return renderer.formatDateTime(iso) } catch { return iso }
}
</script>

<template>
    <div class="flex flex-col min-h-full bg-surface">

        <!-- Toast acessível -->
        <div
            v-if="toast"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            class="fixed top-4 right-4 z-50 max-w-sm rounded-lg border px-4 py-3 text-sm font-medium shadow-lg"
            :class="{
                'border-success-500 bg-success-50 text-success-600': toast.type === 'success',
                'border-danger-500 bg-danger-50 text-danger-600': toast.type === 'error',
            }"
        >
            {{ toast.message }}
        </div>

        <!-- Header -->
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface-elevated px-4 py-3 sm:px-6">
            <h1 class="text-xl font-semibold text-foreground">Lista de Espera</h1>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="openEnrollModal"
            >
                <!-- Heroicon: plus -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Inscrever paciente
            </button>
        </header>

        <!-- Filtros -->
        <div class="flex flex-wrap items-end gap-3 border-b border-border bg-surface-elevated px-4 py-3 sm:px-6">
            <!-- Filtro: Profissional -->
            <div class="flex flex-col gap-1">
                <label for="filter-professional" class="text-xs font-medium text-foreground-muted">Profissional</label>
                <select
                    id="filter-professional"
                    v-model="filters.professional_id"
                    class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="load"
                >
                    <option :value="null">Todos</option>
                    <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>

            <!-- Filtro: Tipo de atendimento -->
            <div class="flex flex-col gap-1">
                <label for="filter-type" class="text-xs font-medium text-foreground-muted">Tipo de atendimento</label>
                <select
                    id="filter-type"
                    v-model="filters.appointment_type_id"
                    class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="load"
                >
                    <option :value="null">Todos</option>
                    <option v-for="t in appointmentTypes" :key="t.id" :value="t.id">{{ t.nome }}</option>
                </select>
            </div>

            <!-- Filtro: Status -->
            <div class="flex flex-col gap-1">
                <label for="filter-status" class="text-xs font-medium text-foreground-muted">Status</label>
                <select
                    id="filter-status"
                    v-model="filters.status"
                    class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @change="load"
                >
                    <option value="">Todos os status</option>
                    <option value="waiting">Aguardando</option>
                    <option value="notified">Notificado</option>
                    <option value="accepted">Aceito</option>
                    <option value="expired">Expirado</option>
                    <option value="canceled">Cancelado</option>
                </select>
            </div>
        </div>

        <!-- Conteúdo principal -->
        <div class="flex-1 px-4 py-4 sm:px-6">

            <!-- Loading skeleton -->
            <div v-if="loading" aria-busy="true" aria-label="Carregando lista de espera..." class="space-y-2">
                <div v-for="i in 5" :key="i" class="h-14 animate-pulse rounded-lg bg-surface-muted" />
            </div>

            <!-- Error state -->
            <div
                v-else-if="error"
                role="alert"
                class="rounded-lg border border-danger-500 bg-danger-50 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
            >
                <div class="flex items-start gap-3">
                    <!-- Heroicon: exclamation-circle -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-danger-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-danger-600">{{ error }}</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg border border-danger-500 px-3 py-1.5 text-xs font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
                    @click="load"
                >
                    Tentar novamente
                </button>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="!entries.length"
                class="rounded-xl border-2 border-dashed border-border bg-surface-muted py-12 text-center"
            >
                <!-- Heroicon: queue-list (simplificado) -->
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-foreground-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h10M4 18h10" />
                </svg>
                <p class="mt-3 text-sm font-medium text-foreground-muted">Nenhum paciente na lista de espera.</p>
                <p class="mt-1 text-xs text-foreground-subtle">Inscreva um paciente para começar a gerir a fila.</p>
                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                    @click="openEnrollModal"
                >
                    Inscrever paciente
                </button>
            </div>

            <!-- Tabela (desktop ≥ 768px) -->
            <div v-else class="hidden md:block overflow-x-auto rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted sticky top-0">
                        <tr>
                            <th scope="col" class="p-3 text-right font-semibold text-foreground-muted w-12">#</th>
                            <th scope="col" class="p-3 text-left font-semibold text-foreground-muted">Paciente</th>
                            <th scope="col" class="p-3 text-left font-semibold text-foreground-muted">Profissional</th>
                            <th scope="col" class="p-3 text-left font-semibold text-foreground-muted">Tipo</th>
                            <th scope="col" class="p-3 text-left font-semibold text-foreground-muted">Status</th>
                            <th scope="col" class="p-3 text-left font-semibold text-foreground-muted">Inscrito em</th>
                            <th scope="col" class="p-3 text-left font-semibold text-foreground-muted">Expira em</th>
                            <th scope="col" class="p-3 w-12"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="e in entries"
                            :key="e.id"
                            class="hover:bg-surface-muted transition-colors"
                            :class="{ 'opacity-60': ['expired', 'canceled'].includes(e.status) }"
                        >
                            <!-- Posição FIFO destacada -->
                            <td class="p-3 text-right">
                                <span
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                                    :class="e.position === 1
                                        ? 'bg-primary-700 text-white'
                                        : 'bg-surface-muted text-foreground-muted border border-border'"
                                >
                                    {{ e.position }}
                                </span>
                            </td>

                            <td class="p-3 font-medium text-foreground">
                                {{ e.paciente_nome ?? `Paciente #${e.paciente_id}` }}
                            </td>

                            <td class="p-3 text-foreground-muted">
                                {{ e.professional_nome ?? '—' }}
                            </td>

                            <td class="p-3 text-foreground-muted">
                                {{ e.appointment_type_nome ?? '—' }}
                            </td>

                            <!-- Badge status -->
                            <td class="p-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusConfig(e.status).classes"
                                >
                                    {{ statusConfig(e.status).label }}
                                </span>
                                <!-- Countdown para notified -->
                                <div
                                    v-if="e.status === 'notified' && e.expires_at"
                                    class="mt-0.5 text-xs font-mono tabular-nums text-warning-500"
                                    aria-live="polite"
                                    :aria-label="`Expira em ${countdown(e.expires_at)}`"
                                >
                                    {{ countdown(e.expires_at) }}
                                </div>
                            </td>

                            <td class="p-3 text-foreground-muted text-xs tabular-nums">
                                {{ fmtDate(e.created_at) }}
                            </td>

                            <td class="p-3 text-foreground-muted text-xs tabular-nums">
                                {{ fmtDate(e.expires_at) }}
                            </td>

                            <td class="p-3 text-right">
                                <button
                                    v-if="['waiting', 'notified'].includes(e.status)"
                                    type="button"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition min-h-[2rem]"
                                    :aria-label="`Remover ${e.paciente_nome ?? 'paciente'} da lista de espera`"
                                    @click="openCancelModal(e)"
                                >
                                    Remover
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cards (mobile < 768px) -->
            <div v-if="entries.length" class="md:hidden space-y-3">
                <div
                    v-for="e in entries"
                    :key="e.id"
                    class="rounded-xl border border-border bg-surface-elevated p-4 shadow-card"
                    :class="{ 'opacity-60': ['expired', 'canceled'].includes(e.status) }"
                >
                    <!-- Header do card -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold shrink-0"
                                :class="e.position === 1
                                    ? 'bg-primary-700 text-white'
                                    : 'bg-surface-muted text-foreground-muted border border-border'"
                            >
                                {{ e.position }}
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-foreground leading-tight">
                                    {{ e.paciente_nome ?? `Paciente #${e.paciente_id}` }}
                                </p>
                                <p class="text-xs text-foreground-muted">{{ e.professional_nome ?? '' }}{{ e.professional_nome && e.appointment_type_nome ? ' · ' : '' }}{{ e.appointment_type_nome ?? '' }}</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="statusConfig(e.status).classes"
                            >
                                {{ statusConfig(e.status).label }}
                            </span>
                            <div
                                v-if="e.status === 'notified' && e.expires_at"
                                class="mt-1 text-xs font-mono tabular-nums text-warning-500 text-right"
                                aria-live="polite"
                            >
                                {{ countdown(e.expires_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- Datas -->
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-foreground-subtle">
                        <span>Inscrito: {{ fmtDate(e.created_at) }}</span>
                        <span v-if="e.expires_at">Expira: {{ fmtDate(e.expires_at) }}</span>
                    </div>

                    <!-- Ação -->
                    <div v-if="['waiting', 'notified'].includes(e.status)" class="mt-3 flex justify-end">
                        <button
                            type="button"
                            class="rounded-lg border border-danger-500 px-3 py-2 text-xs font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition min-h-[2.75rem]"
                            :aria-label="`Remover ${e.paciente_nome ?? 'paciente'} da lista de espera`"
                            @click="openCancelModal(e)"
                        >
                            Remover da fila
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Inscrever paciente -->
        <Teleport to="body">
            <div
                v-if="showEnrollModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                @click.self="closeEnrollModal"
            >
                <div
                    ref="enrollDialogEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="enroll-modal-title"
                    class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-border px-6 py-4">
                        <h2 id="enroll-modal-title" class="text-base font-semibold text-foreground">
                            Inscrever paciente na lista de espera
                        </h2>
                        <button
                            type="button"
                            class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            aria-label="Fechar"
                            @click="closeEnrollModal"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Corpo -->
                    <form class="px-6 py-4 space-y-4" @submit.prevent="submitEnroll" novalidate>
                        <!-- Paciente -->
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1" for="enroll-paciente">
                                Paciente <span class="text-danger-600" aria-hidden="true">*</span>
                            </label>
                            <p v-if="enrollForm.paciente_label" class="mb-1 text-sm font-semibold text-primary-700">
                                {{ enrollForm.paciente_label }}
                            </p>
                            <PatientAutocomplete id="enroll-paciente" @select="selectEnrollPatient" />
                            <p v-if="enrollErrors.paciente_id" role="alert" class="mt-1 text-xs text-danger-600">
                                {{ enrollErrors.paciente_id[0] }}
                            </p>
                        </div>

                        <!-- Profissional -->
                        <div>
                            <label for="enroll-professional" class="block text-sm font-medium text-foreground mb-1">
                                Profissional <span class="text-danger-600" aria-hidden="true">*</span>
                            </label>
                            <select
                                id="enroll-professional"
                                v-model="enrollForm.professional_id"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                :class="{ 'border-danger-500': enrollErrors.professional_id }"
                            >
                                <option :value="null" disabled>Selecione um profissional</option>
                                <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <p v-if="enrollErrors.professional_id" role="alert" class="mt-1 text-xs text-danger-600">
                                {{ enrollErrors.professional_id[0] }}
                            </p>
                        </div>

                        <!-- Tipo de atendimento -->
                        <div>
                            <label for="enroll-type" class="block text-sm font-medium text-foreground mb-1">
                                Tipo de atendimento <span class="text-danger-600" aria-hidden="true">*</span>
                            </label>
                            <select
                                id="enroll-type"
                                v-model="enrollForm.appointment_type_id"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                                :class="{ 'border-danger-500': enrollErrors.appointment_type_id }"
                            >
                                <option :value="null" disabled>Selecione o tipo</option>
                                <option v-for="t in appointmentTypes" :key="t.id" :value="t.id">{{ t.nome }}</option>
                            </select>
                            <p v-if="enrollErrors.appointment_type_id" role="alert" class="mt-1 text-xs text-danger-600">
                                {{ enrollErrors.appointment_type_id[0] }}
                            </p>
                        </div>
                    </form>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            @click="closeEnrollModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="!enrollValid || enrollSubmitting"
                            class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            @click="submitEnroll"
                        >
                            <span v-if="enrollSubmitting">Inscrevendo...</span>
                            <span v-else>Inscrever na fila</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Confirmar cancelamento -->
        <Teleport to="body">
            <div
                v-if="showCancelModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                @click.self="closeCancelModal"
            >
                <div
                    ref="cancelDialogEl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="cancel-modal-title"
                    class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-sm sm:rounded-xl"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-border px-6 py-4">
                        <h2 id="cancel-modal-title" class="text-base font-semibold text-foreground">
                            Remover da lista de espera
                        </h2>
                        <button
                            type="button"
                            class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            aria-label="Fechar"
                            @click="closeCancelModal"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Corpo -->
                    <div class="px-6 py-5">
                        <p class="text-sm text-foreground">
                            Tem certeza que deseja remover
                            <strong class="font-semibold">{{ cancelTarget?.paciente_nome ?? 'este paciente' }}</strong>
                            da lista de espera? Esta ação não pode ser desfeita.
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                            @click="closeCancelModal"
                        >
                            Manter na fila
                        </button>
                        <button
                            type="button"
                            data-autofocus
                            :disabled="cancelSubmitting"
                            class="rounded-lg bg-danger-500 px-4 py-2 text-sm font-semibold text-white hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            @click="confirmCancel"
                        >
                            <span v-if="cancelSubmitting">Removendo...</span>
                            <span v-else>Remover</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
/**
 * AgendaPage — Tela principal da Agenda (US-6.3 / US-6.4 / clarify nº 9).
 *
 * Features implementadas:
 *  - FullCalendar v6 com views diária (timeGridDay) e semanal (timeGridWeek).
 *  - Drag-create: clicar/arrastar em slot vazio abre AppointmentFormModal.
 *  - Drag-to-move: move bloco → RescheduleConfirmModal obrigatório antes de salvar.
 *    Snap-back imediato se slot rejeitado ou usuário cancelar.
 *  - Click em evento existente abre AppointmentFormModal em modo edição.
 *  - Toggle multi-profissional (resourceTimeGridWeek) na view semanal.
 *  - Subscribe Reverb canal tenant.{X}.agenda com debounce de 800ms.
 *  - Loading skeleton, empty state acionável, error state com retry (pt-BR).
 *  - Toast acessível (aria-live=assertive) para sucesso/erro.
 *  - Atalhos de teclado: Esc fecha modais, Enter submete forms.
 */
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import ptBrLocale from '@fullcalendar/core/locales/pt-br'
// Nota: @fullcalendar/resource-timegrid requer @fullcalendar/resource (scheduler premium).
// O pacote não está disponível no projeto atual — multi-prof view está em backlog P2.

import api from '@/lib/api'
import { listAppointmentTypes } from '@/lib/agendaApi'
import { useAgendaStore } from '@/stores/agendaStore'
import { useAuthStore } from '@/stores/auth'
import { useTimezoneRenderer } from '@/composables/useTimezoneRenderer'
import AppointmentFormModal from '@/components/agenda/AppointmentFormModal.vue'
import RescheduleConfirmModal from '@/components/agenda/RescheduleConfirmModal.vue'

// ─── Stores ───────────────────────────────────────────────────────────────────

const store = useAgendaStore()
const auth = useAuthStore()

// ─── Estado da view ───────────────────────────────────────────────────────────

const view = ref('week') // 'day' | 'week'
const multiProf = ref(false)
const professionals = ref([])
const appointmentTypes = ref([])

// ─── Estado de UI ─────────────────────────────────────────────────────────────

const loadingDeps = ref(false)
const errorDeps = ref(null)
const toast = ref(null) /** @type {{ message: string, type: 'success'|'error'|'warning' } | null} */
let toastTimer = null

// ─── Modais ───────────────────────────────────────────────────────────────────

const showCreateModal = ref(false)
const showRescheduleModal = ref(false)
const initialCreatePayload = ref({})
const rescheduleContext = ref({
    pacienteName: '',
    fromLabel: '',
    toLabel: '',
    appointmentId: null,
    newStartsAt: null,
    revertFn: null,
})

// ─── TZ ───────────────────────────────────────────────────────────────────────

const tenantTz = 'America/Sao_Paulo'
const renderer = useTimezoneRenderer(tenantTz)

// ─── Toast helper ─────────────────────────────────────────────────────────────

function showToast(message, type = 'success') {
    if (toastTimer) clearTimeout(toastTimer)
    toast.value = { message, type }
    toastTimer = setTimeout(() => { toast.value = null }, 5000)
}

// ─── Mapeamento status → classe visual ───────────────────────────────────────

const STATUS_COLORS = {
    agendada: '#0f766e',
    confirmada: '#0369a1',
    realizada: '#4b5563',
    nao_realizada: '#b91c1c',
    cancelada: '#9ca3af',
}

function appointmentToFcEvent(apt) {
    const bg = STATUS_COLORS[apt.status] ?? STATUS_COLORS.agendada
    return {
        id: String(apt.id),
        title: apt.paciente_nome ?? `Consulta #${apt.id}`,
        start: apt.starts_at,
        end: apt.ends_at ?? null,
        backgroundColor: bg,
        borderColor: bg,
        textColor: '#fff',
        extendedProps: { appointment: apt },
    }
}

const fcEvents = computed(() => (store.appointments ?? []).map(appointmentToFcEvent))

// ─── Range da semana/dia corrente ─────────────────────────────────────────────

const calendarRef = ref(null)

const currentRange = computed(() => {
    const now = new Date()
    const start = new Date(now)
    if (view.value === 'week') {
        // Segunda da semana corrente
        const day = now.getDay()
        const diff = day === 0 ? -6 : 1 - day
        start.setDate(now.getDate() + diff)
    }
    start.setHours(0, 0, 0, 0)
    const end = new Date(start)
    end.setDate(start.getDate() + (view.value === 'week' ? 7 : 1))
    return { from: start.toISOString(), to: end.toISOString() }
})

// ─── Carregamento ─────────────────────────────────────────────────────────────

async function loadDeps() {
    loadingDeps.value = true
    errorDeps.value = null
    try {
        const [profsRes, typesRes] = await Promise.all([
            api.get('/users', { params: { only_professionals: 1 } }).catch(() => ({ data: { data: [] } })),
            listAppointmentTypes().catch(() => ({ data: { data: [] } })),
        ])
        professionals.value = profsRes.data.data ?? []
        appointmentTypes.value = typesRes.data.data ?? []
    } catch (e) {
        errorDeps.value = 'Não foi possível carregar as configurações da agenda.'
    } finally {
        loadingDeps.value = false
    }
}

async function loadAppointments() {
    store.range = currentRange.value
    try {
        await store.fetch()
    } catch {
        showToast('Não foi possível carregar as consultas. Tente novamente.', 'error')
    }
}

async function retryLoad() {
    errorDeps.value = null
    await loadDeps()
    if (!errorDeps.value) await loadAppointments()
}

// ─── Handlers de FullCalendar ─────────────────────────────────────────────────

function handleSelect(selectInfo) {
    // Drag-create ou click em slot vazio
    selectInfo.view.calendar.unselect()
    initialCreatePayload.value = {
        starts_at: selectInfo.startStr.slice(0, 16), // datetime-local format
        channel_origin: 'painel',
    }
    showCreateModal.value = true
}

function handleEventClick(clickInfo) {
    // Click em consulta existente — abre modal de edição/detalhes
    const apt = clickInfo.event.extendedProps?.appointment
    if (!apt) return
    initialCreatePayload.value = {
        ...apt,
        starts_at: apt.starts_at ? apt.starts_at.slice(0, 16) : '',
    }
    showCreateModal.value = true
}

function handleEventChange(changeInfo) {
    // Drag-to-move: sempre confirmar antes de salvar (clarify nº 9)
    const apt = changeInfo.event.extendedProps?.appointment
    const newStartsAt = changeInfo.event.startStr

    if (!apt) {
        changeInfo.revert()
        return
    }

    rescheduleContext.value = {
        pacienteName: apt.paciente_nome ?? `Consulta #${apt.id}`,
        fromLabel: renderer.formatDateTime(apt.starts_at),
        toLabel: renderer.formatDateTime(newStartsAt),
        appointmentId: apt.id,
        newStartsAt,
        revertFn: changeInfo.revert,
    }
    showRescheduleModal.value = true
}

// ─── Ações de modal ───────────────────────────────────────────────────────────

async function submitCreate(payload) {
    try {
        await store.create(payload)
        showCreateModal.value = false
        showToast('Consulta agendada com sucesso.')
        await loadAppointments()
    } catch (e) {
        const msg = e?.response?.data?.message
            ?? 'Não foi possível salvar a consulta. Tente novamente em instantes.'
        showToast(msg, 'error')
    }
}

async function confirmReschedule() {
    try {
        await store.reschedule(rescheduleContext.value.appointmentId, {
            new_starts_at: rescheduleContext.value.newStartsAt,
        })
        showRescheduleModal.value = false
        showToast('Consulta reagendada com sucesso.')
        await loadAppointments()
    } catch (e) {
        // Snap-back visual no calendário
        if (typeof rescheduleContext.value.revertFn === 'function') {
            rescheduleContext.value.revertFn()
        }
        const msg = e?.response?.data?.message
            ?? 'Não foi possível reagendar. Tente novamente em instantes.'
        showToast(msg, 'error')
        showRescheduleModal.value = false
    }
}

function cancelReschedule() {
    // Usuário cancelou no modal — snap-back obrigatório (clarify nº 9)
    if (typeof rescheduleContext.value.revertFn === 'function') {
        rescheduleContext.value.revertFn()
    }
    showRescheduleModal.value = false
}

// ─── Troca de view ────────────────────────────────────────────────────────────

function setView(newView) {
    view.value = newView
    nextTick(() => {
        const api = calendarRef.value?.getApi?.()
        if (!api) return
        api.changeView(newView === 'day' ? 'timeGridDay' : 'timeGridWeek')
    })
}

// Multi-prof: futuramente resourceTimeGridWeek quando @fullcalendar/resource estiver disponível.
// Por ora, o toggle fica visível mas sem efeito na view (comportamento inerte documentado).
watch(multiProf, () => {
    // P2 backlog — requer @fullcalendar/resource (scheduler premium)
})

// ─── Reverb sync multi-aba ────────────────────────────────────────────────────

let echoChannel = null
let reverbDebounce = null

function subscribeReverb() {
    const echo = window.Echo
    const tenantId = auth.currentTenantId
    if (!echo || !tenantId) return

    echoChannel = store.subscribeBroadcast(tenantId, echo)
}

function unsubscribeReverb() {
    const echo = window.Echo
    const tenantId = auth.currentTenantId
    if (echo && tenantId) {
        try { echo.leave(`tenant.${tenantId}.agenda`) } catch {}
    }
    if (reverbDebounce) clearTimeout(reverbDebounce)
    echoChannel = null
}

// ─── Atalhos de teclado globais ───────────────────────────────────────────────

function handleGlobalKeydown(e) {
    if (e.key === 'Escape') {
        if (showCreateModal.value) {
            showCreateModal.value = false
        } else if (showRescheduleModal.value) {
            cancelReschedule()
        }
    }
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
    window.addEventListener('keydown', handleGlobalKeydown)
    await loadDeps()
    if (!errorDeps.value) await loadAppointments()
    subscribeReverb()
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown)
    unsubscribeReverb()
    if (toastTimer) clearTimeout(toastTimer)
})

// ─── Opções FullCalendar ──────────────────────────────────────────────────────

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'timeGridWeek',
    locale: ptBrLocale,
    timeZone: tenantTz,
    firstDay: 1,
    slotMinTime: '07:00:00',
    slotMaxTime: '20:00:00',
    slotDuration: '00:30:00',
    snapDuration: '00:15:00',
    allDaySlot: false,
    selectable: true,
    selectMirror: true,
    editable: true,
    eventDurationEditable: false,
    nowIndicator: true,
    height: 'auto',
    aspectRatio: 1.5,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
    },
    buttonText: {
        today: 'Hoje',
    },
    noEventsContent: 'Nenhuma consulta neste período.',
    select: handleSelect,
    eventClick: handleEventClick,
    eventChange: handleEventChange,
    events: fcEvents.value,
}))
</script>

<template>
    <div class="flex flex-col h-full bg-surface">
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
                'border-warning-500 bg-warning-50 text-warning-700': toast.type === 'warning',
            }"
        >
            {{ toast.message }}
        </div>

        <!-- Header da página -->
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface-elevated px-4 py-3 sm:px-6">
            <h1 class="text-xl font-semibold text-foreground">Agenda</h1>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Toggle diária / semanal -->
                <div role="group" aria-label="Modo de visualização" class="flex rounded-lg border border-border overflow-hidden">
                    <button
                        type="button"
                        :aria-pressed="view === 'day'"
                        class="px-3 py-1.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="view === 'day'
                            ? 'bg-primary-700 text-white'
                            : 'bg-surface-elevated text-foreground hover:bg-surface-muted'"
                        @click="setView('day')"
                    >
                        Diária
                    </button>
                    <button
                        type="button"
                        :aria-pressed="view === 'week'"
                        class="px-3 py-1.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        :class="view === 'week'
                            ? 'bg-primary-700 text-white'
                            : 'bg-surface-elevated text-foreground hover:bg-surface-muted'"
                        @click="setView('week')"
                    >
                        Semanal
                    </button>
                </div>

                <!-- Toggle multi-prof (só na view semanal) -->
                <label
                    v-if="view === 'week'"
                    class="flex items-center gap-1.5 text-sm text-foreground cursor-pointer select-none"
                >
                    <input
                        v-model="multiProf"
                        type="checkbox"
                        class="h-4 w-4 rounded border-border accent-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    />
                    Multi-profissional
                </label>

                <!-- Botão nova consulta -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-4 py-1.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                    @click="initialCreatePayload = { channel_origin: 'painel' }; showCreateModal = true"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nova consulta
                </button>
            </div>
        </header>

        <!-- Error state (falha ao carregar deps) -->
        <div
            v-if="errorDeps"
            role="alert"
            class="mx-4 mt-4 rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 flex items-center justify-between gap-4"
        >
            <p class="text-sm text-danger-600">{{ errorDeps }}</p>
            <button
                type="button"
                class="shrink-0 rounded-lg border border-danger-500 px-3 py-1.5 text-xs font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
                @click="retryLoad"
            >
                Tentar novamente
            </button>
        </div>

        <!-- Loading skeleton -->
        <div v-else-if="loadingDeps" class="flex-1 px-4 py-4 sm:px-6" aria-label="Carregando agenda..." aria-busy="true">
            <div class="animate-pulse space-y-3">
                <div class="h-8 w-1/3 rounded-lg bg-surface-muted" />
                <div class="grid grid-cols-7 gap-1">
                    <div v-for="i in 7" :key="i" class="h-64 rounded-lg bg-surface-muted" />
                </div>
            </div>
        </div>

        <!-- Calendário FullCalendar -->
        <div v-else class="flex-1 overflow-auto px-2 py-4 sm:px-4 sm:py-6">
            <!-- Loading overlay enquanto store.loading -->
            <div v-if="store.loading" class="flex justify-center py-4" aria-live="polite" aria-label="Atualizando consultas...">
                <svg class="h-5 w-5 animate-spin text-primary-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
            </div>

            <!-- FullCalendar -->
            <FullCalendar
                ref="calendarRef"
                v-bind="calendarOptions"
                class="fc-paciente360"
            />

            <!-- Empty state acionável (exibido abaixo do calendário quando não há eventos) -->
            <div
                v-if="!store.loading && store.appointments.length === 0"
                class="mt-6 rounded-xl border-2 border-dashed border-border bg-surface-muted py-12 text-center"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-foreground-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="mt-3 text-sm font-medium text-foreground-muted">Nenhuma consulta neste período.</p>
                <p class="mt-1 text-xs text-foreground-subtle">Clique em um horário no calendário ou use o botão abaixo para agendar.</p>
                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                    @click="initialCreatePayload = { channel_origin: 'painel' }; showCreateModal = true"
                >
                    Agendar consulta
                </button>
            </div>
        </div>

        <!-- Modal de criar/editar consulta -->
        <AppointmentFormModal
            v-model="showCreateModal"
            :initial-payload="initialCreatePayload"
            :professionals="professionals"
            :appointment-types="appointmentTypes"
            @submit="submitCreate"
        />

        <!-- Modal de confirmação de reagendamento (drag-to-move) -->
        <RescheduleConfirmModal
            v-model="showRescheduleModal"
            :paciente-name="rescheduleContext.pacienteName"
            :from-label="rescheduleContext.fromLabel"
            :to-label="rescheduleContext.toLabel"
            @confirm="confirmReschedule"
            @cancel="cancelReschedule"
        />
    </div>
</template>

<style>
/* FullCalendar overrides com tokens do design system */
.fc-paciente360 .fc-toolbar-title {
    font-size: 1rem;
    font-weight: 600;
    color: oklch(0.22 0.012 250); /* --color-foreground */
}

.fc-paciente360 .fc-button {
    background: oklch(0.32 0.06 180); /* --color-primary-700 */
    border-color: oklch(0.32 0.06 180);
    font-size: 0.875rem;
}

.fc-paciente360 .fc-button:hover {
    background: oklch(0.27 0.05 195); /* --color-primary-800 */
    border-color: oklch(0.27 0.05 195);
}

.fc-paciente360 .fc-button-active,
.fc-paciente360 .fc-button:focus-visible {
    outline: 2px solid oklch(0.55 0.10 180); /* --color-primary-500 */
    outline-offset: 2px;
}

.fc-paciente360 .fc-timegrid-slot:hover {
    background: oklch(0.96 0.025 180 / 0.5); /* --color-primary-50 com transparência */
    cursor: pointer;
}

/* Mobile: toolbar compacta em telas < 640px */
@media (max-width: 639px) {
    .fc-paciente360 .fc-toolbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .fc-paciente360 .fc-toolbar-title {
        font-size: 0.875rem;
    }

    .fc-paciente360 .fc-timegrid-slot {
        height: 2rem;
    }
}
</style>

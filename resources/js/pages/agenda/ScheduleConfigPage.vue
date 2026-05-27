<script setup>
/**
 * T047 — Página de configuração de agenda do profissional (US-6.1).
 *
 * Refinamentos UX (auditoria 2026-05-13 — Lote B):
 *  - Loading skeleton + empty state "Configure sua agenda" CTA (P0)
 *  - Error retry em pt-BR (P0)
 *  - Picker de profissional dropdown (P0)
 *  - Toast feedback save/exception create/delete (P1)
 *  - Editor de blocks por dia com validação inline de overlap (P1)
 *  - Botão "Copiar de outro dia" (Mon→Tue-Fri) (P1)
 *  - Lista de exceções com timeline visual + cores por tipo (P1)
 *  - Modal de criar exceção via ScheduleExceptionForm a11y (P1)
 *  - Mobile: editor empilhado por dia (accordion collapsible) (P1)
 *  - Atalho Ctrl+S salva (P1)
 *  - Sem confirm() / prompt() nativos (P0)
 */
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import {
  getSchedule,
  updateSchedule,
  listExceptions,
  createException,
  deleteException,
} from '@/lib/agendaApi'
import api from '@/lib/api'
import { useTimezoneRenderer } from '@/composables/useTimezoneRenderer'
import ScheduleExceptionForm from '@/components/agenda/ScheduleExceptionForm.vue'

// ─── TZ ───────────────────────────────────────────────────────────────────────

const renderer = useTimezoneRenderer('America/Sao_Paulo')

// ─── Dados dos dias ────────────────────────────────────────────────────────────

const DAYS = [
  { id: 1, label: 'Segunda-feira' },
  { id: 2, label: 'Terça-feira' },
  { id: 3, label: 'Quarta-feira' },
  { id: 4, label: 'Quinta-feira' },
  { id: 5, label: 'Sexta-feira' },
  { id: 6, label: 'Sábado' },
  { id: 7, label: 'Domingo' },
]

// ─── Profissionais ────────────────────────────────────────────────────────────

const professionals = ref([])
const professionalId = ref(null)
const loadingProfs = ref(false)

async function loadProfessionals() {
  loadingProfs.value = true
  try {
    const { data } = await api.get('/users', { params: { only_professionals: 1 } })
    professionals.value = data.data ?? []
    if (professionals.value.length > 0 && !professionalId.value) {
      professionalId.value = professionals.value[0].id
    }
  } catch {
    professionals.value = []
  } finally {
    loadingProfs.value = false
  }
}

// ─── Agenda ───────────────────────────────────────────────────────────────────

const schedule = ref({ schedules: [], accepted_appointment_type_ids: [], timezone: 'America/Sao_Paulo' })
const exceptions = ref([])
const loading = ref(false)
const error = ref(null)
const saving = ref(false)

async function loadSchedule(id) {
  if (!id) return
  loading.value = true
  error.value = null
  try {
    const [scheduleRes, exceptionsRes] = await Promise.all([
      getSchedule(id),
      listExceptions(id),
    ])
    schedule.value = scheduleRes.data.data
    exceptions.value = exceptionsRes.data.data
  } catch {
    error.value = 'Não foi possível carregar a agenda. Verifique sua conexão e tente novamente.'
  } finally {
    loading.value = false
  }
}

function onProfessionalChange() {
  if (professionalId.value) loadSchedule(professionalId.value)
}

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref(null)
let toastTimer = null

function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => { toast.value = null }, 5000)
}

// ─── Blocks editor ────────────────────────────────────────────────────────────

function getDaySchedule(dayOfWeek) {
  return schedule.value.schedules.find((s) => s.day_of_week === dayOfWeek)
}

function getDayBlocks(dayOfWeek) {
  return getDaySchedule(dayOfWeek)?.blocks ?? []
}

function addBlockToDay(dayOfWeek) {
  let day = schedule.value.schedules.find((s) => s.day_of_week === dayOfWeek)
  if (!day) {
    day = { day_of_week: dayOfWeek, blocks: [] }
    schedule.value.schedules.push(day)
  }
  day.blocks.push({ start: '08:00', end: '12:00' })
  blockErrors.value = {}
}

function removeBlock(dayOfWeek, idx) {
  const day = schedule.value.schedules.find((s) => s.day_of_week === dayOfWeek)
  if (day) {
    day.blocks.splice(idx, 1)
    validateBlocks(dayOfWeek)
  }
}

// ─── Validação de overlap ─────────────────────────────────────────────────────

const blockErrors = ref({})

function timeToMinutes(timeStr) {
  const [h, m] = timeStr.split(':').map(Number)
  return h * 60 + m
}

function validateBlocks(dayOfWeek) {
  const blocks = getDayBlocks(dayOfWeek)
  const key = `day_${dayOfWeek}`
  blockErrors.value = { ...blockErrors.value, [key]: null }

  if (blocks.length < 2) return true

  // Ordena por start para verificar overlap
  const sorted = [...blocks].map((b, i) => ({ ...b, idx: i })).sort(
    (a, b) => timeToMinutes(a.start) - timeToMinutes(b.start)
  )

  for (let i = 0; i < sorted.length - 1; i++) {
    const current = sorted[i]
    const next = sorted[i + 1]
    if (timeToMinutes(current.end) > timeToMinutes(next.start)) {
      blockErrors.value = {
        ...blockErrors.value,
        [key]: `Blocos ${current.idx + 1} e ${next.idx + 1} se sobrepõem. Ajuste os horários.`,
      }
      return false
    }
  }

  // Valida start < end em cada bloco
  for (let i = 0; i < blocks.length; i++) {
    if (timeToMinutes(blocks[i].start) >= timeToMinutes(blocks[i].end)) {
      blockErrors.value = {
        ...blockErrors.value,
        [key]: `Bloco ${i + 1}: horário de início deve ser antes do fim.`,
      }
      return false
    }
  }

  return true
}

function onBlockChange(dayOfWeek) {
  validateBlocks(dayOfWeek)
}

// ─── "Copiar de outro dia" ────────────────────────────────────────────────────

const showCopyFromModal = ref(false)
const copyFromDayId = ref(null)
const copyToDays = ref([])
const copyFromDialogEl = ref(null)

function openCopyFromModal(dayId) {
  copyFromDayId.value = dayId
  copyToDays.value = []
  showCopyFromModal.value = true
  nextTick(() => {
    copyFromDialogEl.value?.querySelector('input, button')?.focus()
  })
}

function closeCopyFromModal() {
  showCopyFromModal.value = false
  copyFromDayId.value = null
  copyToDays.value = []
}

function confirmCopyFrom() {
  const sourceDay = getDaySchedule(copyFromDayId.value)
  if (!sourceDay || !sourceDay.blocks.length) {
    showToast('O dia de origem não tem blocos configurados.', 'error')
    closeCopyFromModal()
    return
  }

  copyToDays.value.forEach((targetDayId) => {
    if (targetDayId === copyFromDayId.value) return
    let targetDay = schedule.value.schedules.find((s) => s.day_of_week === targetDayId)
    if (!targetDay) {
      targetDay = { day_of_week: targetDayId, blocks: [] }
      schedule.value.schedules.push(targetDay)
    }
    // Deep clone dos blocos
    targetDay.blocks = sourceDay.blocks.map((b) => ({ ...b }))
  })

  const dayLabel = DAYS.find((d) => d.id === copyFromDayId.value)?.label
  const count = copyToDays.value.filter((d) => d !== copyFromDayId.value).length
  if (count > 0) showToast(`Horários de ${dayLabel} copiados para ${count} dia(s).`)
  closeCopyFromModal()
}

function trapFocus(el, e) {
  if (!el) return
  const focusable = Array.from(
    el.querySelectorAll(
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

// ─── Acordeão mobile ─────────────────────────────────────────────────────────

const expandedDays = ref(new Set([1, 2, 3, 4, 5])) // Seg-Sex expandidos por default

function toggleDay(dayId) {
  if (expandedDays.value.has(dayId)) {
    expandedDays.value.delete(dayId)
  } else {
    expandedDays.value.add(dayId)
  }
  expandedDays.value = new Set(expandedDays.value)
}

// ─── Salvar ───────────────────────────────────────────────────────────────────

const hasBlockErrors = computed(() => Object.values(blockErrors.value).some(Boolean))

async function save() {
  if (!professionalId.value || saving.value) return

  // Valida todos os dias antes de salvar
  let valid = true
  DAYS.forEach((d) => {
    if (!validateBlocks(d.id)) valid = false
  })
  if (!valid) {
    showToast('Há erros nos horários. Corrija antes de salvar.', 'error')
    return
  }

  saving.value = true
  try {
    const res = await updateSchedule(professionalId.value, {
      timezone: schedule.value.timezone ?? 'America/Sao_Paulo',
      schedules: schedule.value.schedules,
      accepted_appointment_type_ids: schedule.value.accepted_appointment_type_ids,
    })
    schedule.value = res.data.data
    showToast('Agenda salva com sucesso.')
  } catch (e) {
    showToast(
      e?.response?.data?.message ?? 'Não foi possível salvar a agenda. Tente novamente.',
      'error'
    )
  } finally {
    saving.value = false
  }
}

// ─── Atalho Ctrl+S ────────────────────────────────────────────────────────────

function handleGlobalKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault()
    save()
  }
}

// ─── Modal de exceção ─────────────────────────────────────────────────────────

const showExceptionModal = ref(false)
const exceptionDialogEl = ref(null)
const exceptionSubmitting = ref(false)
const exceptionErrors = ref({})

// Alvo de exclusão de exceção
const showDeleteExceptionModal = ref(false)
const deleteExceptionTarget = ref(null)
const deletingException = ref(false)
const deleteExceptionDialogEl = ref(null)

function openExceptionModal() {
  exceptionErrors.value = {}
  showExceptionModal.value = true
  nextTick(() => {
    exceptionDialogEl.value?.querySelector('input, select, textarea')?.focus()
  })
}

function closeExceptionModal() {
  showExceptionModal.value = false
  exceptionErrors.value = {}
}

async function handleExceptionSubmit({ data, fieldErrors: setFieldErrors }) {
  if (!professionalId.value) return
  exceptionSubmitting.value = true
  try {
    const res = await createException(professionalId.value, data)
    exceptions.value.push(res.data.data)
    closeExceptionModal()
    showToast('Bloqueio adicionado com sucesso.')
  } catch (e) {
    if (e?.response?.status === 422 && e.response.data?.errors) {
      if (typeof setFieldErrors === 'function') setFieldErrors(e.response.data.errors)
      else exceptionErrors.value = e.response.data.errors
    } else {
      showToast(
        e?.response?.data?.message ?? 'Não foi possível adicionar o bloqueio. Tente novamente.',
        'error'
      )
    }
  } finally {
    exceptionSubmitting.value = false
  }
}

function openDeleteException(ex) {
  deleteExceptionTarget.value = ex
  showDeleteExceptionModal.value = true
  nextTick(() => {
    deleteExceptionDialogEl.value?.querySelector('button[data-autofocus]')?.focus()
  })
}

function closeDeleteException() {
  showDeleteExceptionModal.value = false
  deleteExceptionTarget.value = null
}

async function confirmDeleteException() {
  if (!deleteExceptionTarget.value || deletingException.value) return
  deletingException.value = true
  try {
    await deleteException(deleteExceptionTarget.value.id)
    exceptions.value = exceptions.value.filter((e) => e.id !== deleteExceptionTarget.value.id)
    closeDeleteException()
    showToast('Bloqueio removido.')
  } catch {
    showToast('Não foi possível remover o bloqueio. Tente novamente.', 'error')
  } finally {
    deletingException.value = false
  }
}

// ─── Formatação de exceção ────────────────────────────────────────────────────

function formatExceptionLabel(ex) {
  try {
    const start = renderer.formatDateTime(ex.starts_at)
    const end = renderer.formatDateTime(ex.ends_at)
    return `${start} → ${end}`
  } catch {
    return `${ex.starts_at} → ${ex.ends_at}`
  }
}

function exceptionDurationDays(ex) {
  try {
    const start = new Date(ex.starts_at)
    const end = new Date(ex.ends_at)
    const ms = end - start
    const days = Math.ceil(ms / 86400000)
    if (days <= 1) return null
    return `${days} dias`
  } catch {
    return null
  }
}

// ─── Init ─────────────────────────────────────────────────────────────────────

onMounted(async () => {
  window.addEventListener('keydown', handleGlobalKeydown)
  await loadProfessionals()
  if (professionalId.value) await loadSchedule(professionalId.value)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <h1 class="text-xl font-semibold text-foreground">Configurar Agenda</h1>
      <p class="text-xs text-foreground-muted">Atalho: Ctrl+S para salvar</p>
    </div>

    <!-- Toast -->
    <div
      v-if="toast"
      role="alert"
      aria-live="assertive"
      class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg transition"
      :class="toast.type === 'success' ? 'bg-success-500 text-white' : 'bg-danger-500 text-white'"
    >
      <svg v-if="toast.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      {{ toast.message }}
    </div>

    <!-- Picker de profissional -->
    <div class="rounded-lg border border-border bg-surface-elevated shadow-card p-4">
      <label for="scp-professional" class="block text-sm font-medium text-foreground mb-1">
        Profissional
      </label>
      <div v-if="loadingProfs" class="h-10 animate-pulse rounded-lg bg-surface-muted w-full max-w-xs" />
      <select
        v-else
        id="scp-professional"
        v-model="professionalId"
        class="w-full max-w-xs rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        @change="onProfessionalChange"
      >
        <option value="" disabled>Selecione um profissional</option>
        <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <p v-if="!professionals.length && !loadingProfs" class="mt-1 text-xs text-foreground-muted">
        Nenhum profissional encontrado. Cadastre profissionais antes de configurar a agenda.
      </p>
    </div>

    <!-- Empty state (nenhum profissional selecionado) -->
    <div
      v-if="!professionalId && !loadingProfs"
      class="flex flex-col items-center justify-center rounded-lg border border-border border-dashed bg-surface py-12 text-center"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-foreground-subtle mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <h2 class="text-base font-semibold text-foreground mb-1">Configure sua agenda</h2>
      <p class="text-sm text-foreground-muted max-w-xs">
        Selecione um profissional acima para visualizar e editar os horários de atendimento e bloqueios pontuais.
      </p>
    </div>

    <!-- Loading skeleton -->
    <div v-else-if="loading" aria-busy="true" aria-label="Carregando agenda" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-20 animate-pulse rounded-lg bg-surface-muted" />
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="rounded-lg border border-danger-500/30 bg-danger-50 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-danger-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-sm text-danger-600 flex-1">{{ error }}</p>
      <button
        type="button"
        class="shrink-0 rounded-lg border border-danger-500 px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
        @click="loadSchedule(professionalId)"
      >
        Tentar novamente
      </button>
    </div>

    <!-- Conteúdo da agenda -->
    <template v-else-if="professionalId && !loading && !error">
      <!-- ─── Editor de horários recorrentes ─────────────────────────────── -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-semibold text-foreground">Horários recorrentes</h2>
          <button
            type="button"
            :disabled="saving || hasBlockErrors"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
            @click="save"
          >
            <svg v-if="saving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ saving ? 'Salvando...' : 'Salvar agenda' }}
          </button>
        </div>

        <p v-if="hasBlockErrors" role="alert" class="mb-3 text-sm text-danger-600 flex items-center gap-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Corrija os erros nos horários antes de salvar.
        </p>

        <div class="space-y-2">
          <div
            v-for="day in DAYS"
            :key="day.id"
            class="rounded-lg border border-border bg-surface-elevated shadow-card overflow-hidden"
          >
            <!-- Header do dia (sempre visível) -->
            <button
              type="button"
              class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-surface-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
              :aria-expanded="expandedDays.has(day.id)"
              :aria-controls="`day-blocks-${day.id}`"
              @click="toggleDay(day.id)"
            >
              <div class="flex items-center gap-3">
                <span class="font-medium text-foreground text-sm">{{ day.label }}</span>
                <span
                  v-if="getDayBlocks(day.id).length > 0"
                  class="text-xs text-foreground-muted"
                >
                  {{ getDayBlocks(day.id).length }} bloco(s)
                  <span class="hidden sm:inline">
                    · {{ getDayBlocks(day.id).map(b => `${b.start}–${b.end}`).join(', ') }}
                  </span>
                </span>
                <span v-else class="text-xs text-foreground-subtle italic">Sem horários</span>
              </div>
              <div class="flex items-center gap-2">
                <span
                  v-if="blockErrors[`day_${day.id}`]"
                  class="text-xs text-danger-600 font-medium"
                  aria-hidden="true"
                >Erro</span>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4 text-foreground-muted transition-transform"
                  :class="expandedDays.has(day.id) ? 'rotate-180' : ''"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </button>

            <!-- Conteúdo do dia (collapsível) -->
            <div
              v-if="expandedDays.has(day.id)"
              :id="`day-blocks-${day.id}`"
              class="border-t border-border px-4 py-3 space-y-3"
            >
              <!-- Erro de overlap -->
              <p
                v-if="blockErrors[`day_${day.id}`]"
                role="alert"
                class="text-xs text-danger-600 flex items-center gap-1.5"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ blockErrors[`day_${day.id}`] }}
              </p>

              <!-- Blocos de horário -->
              <div class="space-y-2">
                <div
                  v-for="(block, idx) in getDayBlocks(day.id)"
                  :key="idx"
                  class="flex flex-wrap sm:flex-nowrap items-center gap-2"
                >
                  <div class="flex items-center gap-2 flex-1 min-w-0">
                    <label :for="`block-${day.id}-${idx}-start`" class="sr-only">
                      Horário de início — bloco {{ idx + 1 }} {{ day.label }}
                    </label>
                    <input
                      :id="`block-${day.id}-${idx}-start`"
                      v-model="block.start"
                      type="time"
                      class="flex-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition min-h-[44px]"
                      @change="onBlockChange(day.id)"
                    />
                    <span class="text-foreground-muted text-sm shrink-0">até</span>
                    <label :for="`block-${day.id}-${idx}-end`" class="sr-only">
                      Horário de fim — bloco {{ idx + 1 }} {{ day.label }}
                    </label>
                    <input
                      :id="`block-${day.id}-${idx}-end`"
                      v-model="block.end"
                      type="time"
                      class="flex-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition min-h-[44px]"
                      @change="onBlockChange(day.id)"
                    />
                  </div>
                  <button
                    type="button"
                    class="shrink-0 rounded-lg p-2 text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition min-h-[44px] min-w-[44px] flex items-center justify-center"
                    :aria-label="`Remover bloco ${idx + 1} de ${day.label}`"
                    @click="removeBlock(day.id, idx)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Ações do dia -->
              <div class="flex flex-wrap gap-2 pt-1">
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 text-sm text-primary-700 hover:text-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded transition py-1"
                  @click="addBlockToDay(day.id)"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Adicionar bloco
                </button>
                <button
                  v-if="getDayBlocks(day.id).length > 0"
                  type="button"
                  class="inline-flex items-center gap-1.5 text-sm text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded transition py-1"
                  @click="openCopyFromModal(day.id)"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                  Copiar para outros dias
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ─── Bloqueios pontuais ─────────────────────────────────────────── -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-semibold text-foreground">Bloqueios pontuais</h2>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
            @click="openExceptionModal"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Adicionar bloqueio
          </button>
        </div>

        <!-- Empty state exceções -->
        <div
          v-if="exceptions.length === 0"
          class="rounded-lg border border-border border-dashed bg-surface py-8 text-center"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-foreground-subtle mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
          </svg>
          <p class="text-sm text-foreground-muted">Nenhum bloqueio cadastrado.</p>
          <p class="text-xs text-foreground-subtle mt-1">Use bloqueios para férias, feriados ou ausências pontuais.</p>
        </div>

        <!-- Timeline de exceções -->
        <div v-else class="space-y-2">
          <div
            v-for="ex in exceptions"
            :key="ex.id"
            class="flex items-start gap-3 rounded-lg border border-border bg-surface-elevated shadow-card p-4"
          >
            <!-- Indicador visual de linha do tempo -->
            <div class="flex flex-col items-center gap-1 shrink-0 pt-0.5">
              <div class="h-3 w-3 rounded-full bg-warning-500 ring-2 ring-warning-500/20" aria-hidden="true" />
              <div class="w-0.5 flex-1 bg-border min-h-[20px]" aria-hidden="true" />
            </div>

            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-foreground">
                {{ formatExceptionLabel(ex) }}
                <span v-if="exceptionDurationDays(ex)" class="text-xs text-foreground-muted ml-1">
                  ({{ exceptionDurationDays(ex) }})
                </span>
              </p>
              <p v-if="ex.reason" class="text-xs text-foreground-muted mt-0.5">{{ ex.reason }}</p>
            </div>

            <button
              type="button"
              class="shrink-0 rounded-lg p-1.5 text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition min-h-[36px] min-w-[36px] flex items-center justify-center"
              :aria-label="`Remover bloqueio: ${formatExceptionLabel(ex)}`"
              @click="openDeleteException(ex)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </section>
    </template>

    <!-- ─── Modal de adicionar bloqueio ────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showExceptionModal"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
        @click.self="closeExceptionModal"
        @keydown.esc.prevent="closeExceptionModal"
        @keydown="trapFocus(exceptionDialogEl, $event)"
      >
        <div
          ref="exceptionDialogEl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="exception-modal-title"
          class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
        >
          <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 id="exception-modal-title" class="text-base font-semibold text-foreground">
              Adicionar bloqueio pontual
            </h2>
            <button
              type="button"
              class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
              aria-label="Fechar"
              @click="closeExceptionModal"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-4">
            <ScheduleExceptionForm
              :submitting="exceptionSubmitting"
              :field-errors="exceptionErrors"
              @submit="handleExceptionSubmit"
              @cancel="closeExceptionModal"
            />
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Modal de confirmação de remoção de bloqueio ────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showDeleteExceptionModal"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
        @click.self="closeDeleteException"
        @keydown.esc.prevent="closeDeleteException"
        @keydown="trapFocus(deleteExceptionDialogEl, $event)"
      >
        <div
          ref="deleteExceptionDialogEl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="delete-exception-modal-title"
          class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
        >
          <div class="px-6 py-4 border-b border-border">
            <h2 id="delete-exception-modal-title" class="text-base font-semibold text-foreground">
              Remover bloqueio
            </h2>
          </div>
          <div class="px-6 py-4">
            <p class="text-sm text-foreground">
              Remover o bloqueio de
              <strong class="font-semibold">{{ deleteExceptionTarget ? formatExceptionLabel(deleteExceptionTarget) : '' }}</strong>?
            </p>
            <p v-if="deleteExceptionTarget?.reason" class="text-xs text-foreground-muted mt-1">
              Motivo: {{ deleteExceptionTarget.reason }}
            </p>
            <p class="text-xs text-foreground-muted mt-2">
              O período voltará a aceitar agendamentos após a remoção.
            </p>
          </div>
          <div class="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
            <button
              type="button"
              class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
              @click="closeDeleteException"
            >
              Cancelar
            </button>
            <button
              data-autofocus
              type="button"
              :disabled="deletingException"
              class="rounded-lg bg-danger-500 px-4 py-2 text-sm font-semibold text-white hover:bg-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
              @click="confirmDeleteException"
            >
              <span v-if="deletingException">Removendo...</span>
              <span v-else>Remover bloqueio</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Modal de "Copiar horários para outros dias" ─────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showCopyFromModal"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
        @click.self="closeCopyFromModal"
        @keydown.esc.prevent="closeCopyFromModal"
        @keydown="trapFocus(copyFromDialogEl, $event)"
      >
        <div
          ref="copyFromDialogEl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="copy-modal-title"
          class="w-full rounded-t-2xl bg-surface-elevated shadow-xl sm:max-w-md sm:rounded-xl"
        >
          <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 id="copy-modal-title" class="text-base font-semibold text-foreground">
              Copiar horários de {{ DAYS.find(d => d.id === copyFromDayId)?.label }}
            </h2>
            <button
              type="button"
              class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
              aria-label="Fechar"
              @click="closeCopyFromModal"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-4">
            <p class="text-sm text-foreground-muted mb-3">
              Selecione os dias que receberão os mesmos horários:
            </p>
            <fieldset class="space-y-2">
              <legend class="sr-only">Dias que receberão os horários copiados</legend>
              <label
                v-for="day in DAYS.filter(d => d.id !== copyFromDayId)"
                :key="day.id"
                class="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5 cursor-pointer hover:bg-surface-subtle transition"
              >
                <input
                  v-model="copyToDays"
                  type="checkbox"
                  :value="day.id"
                  class="h-4 w-4 rounded border-border accent-primary-700"
                />
                <span class="text-sm text-foreground">{{ day.label }}</span>
                <span v-if="getDayBlocks(day.id).length > 0" class="ml-auto text-xs text-foreground-muted">
                  ({{ getDayBlocks(day.id).length }} bloco(s) atual)
                </span>
              </label>
            </fieldset>
            <p class="mt-2 text-xs text-foreground-muted">
              Os horários existentes dos dias selecionados serão substituídos.
            </p>
          </div>
          <div class="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
            <button
              type="button"
              class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
              @click="closeCopyFromModal"
            >
              Cancelar
            </button>
            <button
              type="button"
              :disabled="copyToDays.length === 0"
              class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
              @click="confirmCopyFrom"
            >
              Copiar para {{ copyToDays.length }} dia(s)
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
/**
 * T114 — Botão de marcação de comparecimento (clarify nº 14).
 *
 * Refinamentos UX (auditoria 2026-05-13 — Lote D):
 *  - Removido prompt() para motivo no-show → expande inline com textarea (P0)
 *  - Removido confirm() para reverter → mini popover inline (P0)
 *  - Loading states: botão disabled + spinner durante request (P0)
 *  - Erros 422 inline com role=alert (não toast — contexto pequeno) (P0)
 *  - Tooltip no badge auto_flagged_at com explicação clara (clarify nº 14) (P1)
 *  - aria-expanded no botão que abre popover, Esc fecha (P1)
 *  - Interface mantida: prop appointment + @updated (compatível com PendingAttendanceWidget) (P0)
 *
 * Estados:
 *  - status='scheduled'|'confirmed' → 2 botões (Realizada / Não realizada)
 *  - Click "Não realizada" → expande painel inline com textarea + Confirmar/Cancelar
 *  - status='realizada'|'nao_realizada' → status badge + botão "Reverter" (se dentro 48h)
 *  - Click "Reverter" → mini popover com confirmação
 *  - auto_flagged_at preenchido → badge explicativo com tooltip
 */
import { ref, computed, nextTick } from 'vue'
import api from '@/lib/api'

const props = defineProps({
  appointment: { type: Object, required: true },
})
const emit = defineEmits(['updated'])

// ─── Estados computados ───────────────────────────────────────────────────────

const isMarked = computed(() =>
  ['realizada', 'nao_realizada', 'concluida_sem_registro'].includes(props.appointment.status)
)

const isAutoFlagged = computed(() => !!props.appointment.auto_flagged_at)

const statusLabel = computed(() => {
  const map = {
    realizada: 'Realizada',
    nao_realizada: 'Não realizada',
    concluida_sem_registro: 'Concluída sem registro',
  }
  return map[props.appointment.status] ?? props.appointment.status
})

// ─── Painel inline "Não realizada" ───────────────────────────────────────────

const showNoShowPanel = ref(false)
const noShowMotivo = ref('')
const noShowPanelRef = ref(null)
const noShowTextareaRef = ref(null)
const markingNoShow = ref(false)
const noShowError = ref(null)

function openNoShowPanel() {
  noShowMotivo.value = ''
  noShowError.value = null
  showNoShowPanel.value = true
  nextTick(() => {
    noShowTextareaRef.value?.focus()
  })
}

function closeNoShowPanel() {
  showNoShowPanel.value = false
  noShowMotivo.value = ''
  noShowError.value = null
}

function handleNoShowPanelKeydown(e) {
  if (e.key === 'Escape') {
    e.stopPropagation()
    closeNoShowPanel()
  }
}

async function confirmNoShow() {
  if (markingNoShow.value) return
  markingNoShow.value = true
  noShowError.value = null
  try {
    const { data } = await api.post(`/agenda/consultas/${props.appointment.id}/marcar-comparecimento`, {
      status: 'nao_realizada',
      attendance_motivo: noShowMotivo.value.trim() || null,
    })
    closeNoShowPanel()
    emit('updated', data.data)
  } catch (e) {
    if (e?.response?.status === 422 && e.response.data?.errors) {
      const msgs = Object.values(e.response.data.errors).flat()
      noShowError.value = msgs[0] ?? 'Dados inválidos. Verifique e tente novamente.'
    } else {
      noShowError.value = e?.response?.data?.message ?? 'Não foi possível registrar. Tente novamente.'
    }
  } finally {
    markingNoShow.value = false
  }
}

// ─── Marcar como realizada ────────────────────────────────────────────────────

const markingRealizada = ref(false)
const markRealizadaError = ref(null)

async function markRealizada() {
  if (markingRealizada.value) return
  markingRealizada.value = true
  markRealizadaError.value = null
  try {
    const { data } = await api.post(`/agenda/consultas/${props.appointment.id}/marcar-comparecimento`, {
      status: 'realizada',
    })
    emit('updated', data.data)
  } catch (e) {
    markRealizadaError.value = e?.response?.data?.message ?? 'Não foi possível registrar. Tente novamente.'
  } finally {
    markingRealizada.value = false
  }
}

// ─── Popover de reversão ──────────────────────────────────────────────────────

const showRevertPopover = ref(false)
const revertPopoverRef = ref(null)
const reverting = ref(false)
const revertError = ref(null)

function openRevertPopover() {
  revertError.value = null
  showRevertPopover.value = true
  nextTick(() => {
    revertPopoverRef.value?.querySelector('button[data-autofocus]')?.focus()
  })
}

function closeRevertPopover() {
  showRevertPopover.value = false
  revertError.value = null
}

function handleRevertKeydown(e) {
  if (e.key === 'Escape') {
    e.stopPropagation()
    closeRevertPopover()
  }
}

async function confirmRevert() {
  if (reverting.value) return
  reverting.value = true
  revertError.value = null
  try {
    const { data } = await api.post(`/agenda/consultas/${props.appointment.id}/reverter-comparecimento`)
    closeRevertPopover()
    emit('updated', data.data)
  } catch (e) {
    if (e?.response?.status === 422 && e.response.data?.errors) {
      const msgs = Object.values(e.response.data.errors).flat()
      revertError.value = msgs[0] ?? 'Dados inválidos.'
    } else {
      revertError.value = e?.response?.data?.message ?? 'Não foi possível reverter. Tente novamente.'
    }
  } finally {
    reverting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-1.5" @keydown="showNoShowPanel ? handleNoShowPanelKeydown($event) : showRevertPopover ? handleRevertKeydown($event) : null">
    <!-- Badge auto-flagged (com tooltip acessível) -->
    <div v-if="isAutoFlagged" class="flex items-center gap-1">
      <span
        class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-500"
        :title="`Sem confirmação de comparecimento — marcado automaticamente ${appointment.auto_flagged_at ? 'após 30 minutos do horário agendado' : ''}`"
        :aria-label="`Pendente: sem confirmação de comparecimento. Marcado automaticamente após 30 minutos do horário agendado.`"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
        </svg>
        Pendente
      </span>
    </div>

    <!-- Estado: ainda não marcado -->
    <template v-if="!isMarked">
      <div class="flex flex-wrap gap-2">
        <!-- Botão: Realizada -->
        <button
          type="button"
          :disabled="markingRealizada || showNoShowPanel"
          class="inline-flex items-center gap-1.5 rounded-md bg-success-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-success-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-success-500 disabled:opacity-50 disabled:cursor-not-allowed transition min-h-[36px]"
          @click="markRealizada"
        >
          <svg v-if="markingRealizada" class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ markingRealizada ? 'Registrando...' : 'Realizada' }}</span>
        </button>

        <!-- Botão: Não realizada (abre painel inline) -->
        <button
          type="button"
          :disabled="markingRealizada"
          :aria-expanded="showNoShowPanel"
          :aria-controls="`noshow-panel-${appointment.id}`"
          class="inline-flex items-center gap-1.5 rounded-md border border-danger-500/60 bg-danger-50 px-3 py-1.5 text-xs font-semibold text-danger-500 hover:bg-danger-50/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50 disabled:cursor-not-allowed transition min-h-[36px]"
          @click="openNoShowPanel"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Não realizada
        </button>
      </div>

      <!-- Erro ao marcar como realizada -->
      <p v-if="markRealizadaError" role="alert" class="text-xs text-danger-500">
        {{ markRealizadaError }}
      </p>

      <!-- Painel inline "Não realizada" -->
      <div
        v-if="showNoShowPanel"
        :id="`noshow-panel-${appointment.id}`"
        ref="noShowPanelRef"
        role="group"
        aria-label="Registrar não comparecimento"
        class="mt-1 rounded-lg border border-danger-500/30 bg-danger-50 p-3 space-y-2"
      >
        <label :for="`noshow-motivo-${appointment.id}`" class="block text-xs font-medium text-foreground">
          Motivo (opcional)
        </label>
        <textarea
          :id="`noshow-motivo-${appointment.id}`"
          ref="noShowTextareaRef"
          v-model="noShowMotivo"
          rows="2"
          placeholder="Ex.: paciente não atendeu a ligação, desmarcou por mensagem..."
          class="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground placeholder:text-foreground-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 resize-none transition"
          @keydown.enter.ctrl="confirmNoShow"
        />
        <p v-if="noShowError" role="alert" class="text-xs text-danger-500">
          {{ noShowError }}
        </p>
        <div class="flex items-center gap-2 justify-end">
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-xs font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition min-h-[32px]"
            @click="closeNoShowPanel"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="markingNoShow"
            class="inline-flex items-center gap-1.5 rounded-md bg-danger-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-50 disabled:cursor-not-allowed transition min-h-[32px]"
            @click="confirmNoShow"
          >
            <svg v-if="markingNoShow" class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span>{{ markingNoShow ? 'Registrando...' : 'Confirmar não realizada' }}</span>
          </button>
        </div>
        <p class="text-xs text-foreground-muted">Ctrl+Enter para confirmar rapidamente</p>
      </div>
    </template>

    <!-- Estado: já marcado -->
    <template v-else>
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Badge de status -->
        <span
          class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
          :class="{
            'bg-success-50 text-success-600': appointment.status === 'realizada',
            'bg-danger-50 text-danger-500': appointment.status === 'nao_realizada',
            'bg-surface-muted text-foreground-muted': appointment.status === 'concluida_sem_registro',
          }"
        >
          {{ statusLabel }}
        </span>

        <!-- Botão reverter (abre popover) -->
        <div v-if="appointment.status !== 'concluida_sem_registro'" class="relative">
          <button
            type="button"
            :aria-expanded="showRevertPopover"
            :aria-controls="`revert-popover-${appointment.id}`"
            class="text-xs text-primary-700 hover:text-primary-800 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded transition min-h-[32px] px-1"
            @click="openRevertPopover"
          >
            Reverter
          </button>

          <!-- Popover de confirmação de reversão -->
          <div
            v-if="showRevertPopover"
            :id="`revert-popover-${appointment.id}`"
            ref="revertPopoverRef"
            role="dialog"
            :aria-label="`Confirmar reversão da marcação`"
            class="absolute bottom-full left-0 z-20 mb-1 w-64 rounded-lg border border-border bg-surface-elevated shadow-popover p-3 space-y-2"
          >
            <p class="text-xs text-foreground font-medium">Reverter marcação de comparecimento?</p>
            <p class="text-xs text-foreground-muted">
              A consulta voltará para o status anterior. Ação disponível apenas dentro de 48h.
            </p>
            <p v-if="revertError" role="alert" class="text-xs text-danger-500">
              {{ revertError }}
            </p>
            <div class="flex items-center gap-2 justify-end">
              <button
                type="button"
                class="rounded px-2.5 py-1 text-xs font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                @click="closeRevertPopover"
              >
                Cancelar
              </button>
              <button
                data-autofocus
                type="button"
                :disabled="reverting"
                class="inline-flex items-center gap-1 rounded bg-primary-700 px-2.5 py-1 text-xs font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                @click="confirmRevert"
              >
                <svg v-if="reverting" class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span>{{ reverting ? 'Revertendo...' : 'Reverter' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

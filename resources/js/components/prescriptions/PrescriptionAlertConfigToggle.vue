<script setup>
/**
 * T115 (Fase 7 — US-8.2) — Toggle de configuração de alertas de vencimento.
 *
 * Comportamento:
 *  - Switch habilitado apenas para prescription.type === 'common'.
 *  - Para 'special' / 'controlled': switch desabilitado com tooltip e
 *    descrição visível "Alerta obrigatório por Portaria 344/98".
 *  - Ao toggle: PATCH /api/v1/prescriptions/{id}/alert-config.
 *  - Toast local (padrão Fase 6) em sucesso e erro.
 *  - Spinner durante request; reverte switch em erro.
 *  - Lista pequena dos alertas D-15/D-7/D-1 com status e skip_reason.
 *
 * A11y:
 *  - <button role="switch" :aria-checked> para o switch.
 *  - aria-disabled em tipos special/controlled.
 *  - aria-describedby aponta para texto descritivo.
 *  - Toast: role="alert" aria-live="assertive".
 */
import { ref, computed, onMounted } from 'vue'
import { DateTime } from 'luxon'
import { usePrescriptionsStore } from '@/stores/prescriptionsStore'
import { getPrescriptionAlerts } from '@/lib/prescriptionsApi'

const props = defineProps({
  prescription: { type: Object, required: true },
})
const emit = defineEmits(['updated'])

const store = usePrescriptionsStore()

// ─── Estado do switch ─────────────────────────────────────────────────────────

const isCommon = computed(() => props.prescription.type === 'common')

/** true = alertas ativos (alert_disabled === false ou undefined) */
const enabled = ref(!props.prescription.alert_disabled)
const saving = ref(false)

const descriptionId = computed(() => `alert-desc-${props.prescription.id}`)

// ─── Toast local (padrão Fase 6) ─────────────────────────────────────────────

const toast = ref(null)
let toastTimer = null

function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => {
    toast.value = null
  }, 5000)
}

// ─── Toggle handler ───────────────────────────────────────────────────────────

async function toggle() {
  if (!isCommon.value || saving.value) return

  const previousValue = enabled.value
  const newEnabled = !enabled.value
  enabled.value = newEnabled
  saving.value = true

  try {
    const updated = await store.updateAlertConfig(props.prescription.id, !newEnabled)
    showToast(newEnabled ? 'Alertas de vencimento ativados.' : 'Alertas de vencimento desativados.')
    emit('updated', updated)
    // Recarrega alertas após mudança de configuração
    await loadAlerts()
  } catch (e) {
    // Reverte o switch em caso de erro
    enabled.value = previousValue
    const msg = e?.response?.data?.message ?? 'Não foi possível atualizar a configuração de alertas.'
    showToast(msg, 'error')
  } finally {
    saving.value = false
  }
}

// ─── Lista de alertas D-15/D-7/D-1 ───────────────────────────────────────────

const alerts = ref([])
const loadingAlerts = ref(false)

const ALERT_STEP_LABELS = {
  d_minus_15: 'D-15',
  d_minus_7: 'D-7',
  d_minus_1: 'D-1',
}

const ORDERED_STEPS = ['d_minus_15', 'd_minus_7', 'd_minus_1']

/**
 * Monta lista normalizada mantendo D-15, D-7, D-1 sempre presentes,
 * preenchendo gaps com dados inline da prescription se a API não retornou.
 */
const alertRows = computed(() => {
  return ORDERED_STEPS.map((step) => {
    const found = alerts.value.find((a) => a.alert_type === step)
    return (
      found ?? {
        alert_type: step,
        status: null,
        scheduled_for: null,
        dispatched_at: null,
        skip_reason: null,
      }
    )
  })
})

async function loadAlerts() {
  loadingAlerts.value = true
  try {
    const data = await getPrescriptionAlerts(props.prescription.id)
    alerts.value = Array.isArray(data) ? data : []
  } catch {
    // Silencioso — usa prescription.alerts se disponível
    alerts.value = props.prescription.alerts ?? []
  } finally {
    loadingAlerts.value = false
  }
}

onMounted(() => {
  // Sincroniza estado inicial com prop (pode ter mudado antes do mount)
  enabled.value = !props.prescription.alert_disabled
  // Carrega alertas inline se já disponíveis, depois tenta API
  alerts.value = props.prescription.alerts ?? []
  loadAlerts()
})

// ─── Helpers de status ────────────────────────────────────────────────────────

function statusIcon(status) {
  if (status === 'dispatched') return 'check'
  if (status === 'skipped') return 'skip'
  if (status === 'cancelled') return 'cancelled'
  if (status === 'pending') return 'pending'
  if (status?.startsWith('blocked_')) return 'blocked'
  return 'none'
}

function statusLabel(status) {
  const map = {
    pending: 'Pendente',
    dispatched: 'Enviado',
    skipped: 'Ignorado',
    cancelled: 'Cancelado',
    blocked_no_template: 'Bloqueado (sem template)',
    blocked_no_channel: 'Bloqueado (sem canal)',
    blocked_opt_out: 'Bloqueado (opt-out)',
  }
  return map[status] ?? (status ? status : '—')
}

const STATUS_CLASSES = {
  check: 'text-success-600',
  pending: 'text-warning-600',
  skip: 'text-foreground-muted',
  cancelled: 'text-foreground-muted',
  blocked: 'text-danger-600',
  none: 'text-foreground-muted',
}

function fmtDate(iso) {
  if (!iso) return '—'
  const dt = DateTime.fromISO(iso)
  return dt.isValid ? dt.setLocale('pt-BR').toFormat('dd/MM/yyyy') : iso
}
</script>

<template>
  <div class="space-y-4">
    <!-- Cabeçalho + switch -->
    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0 flex-1">
        <h3 class="text-sm font-semibold text-foreground">Alertas de vencimento</h3>
        <p
          :id="descriptionId"
          class="mt-0.5 text-xs text-foreground-muted"
        >
          <template v-if="isCommon">
            Receba notificações 15, 7 e 1 dia antes do vencimento desta receita.
          </template>
          <template v-else>
            Alerta obrigatório por Portaria 344/98 — não pode ser desativado em receitas
            especiais ou controladas.
          </template>
        </p>
      </div>

      <!-- Switch acessível -->
      <div class="shrink-0 flex items-center gap-2">
        <!-- Spinner durante salvamento -->
        <svg
          v-if="saving"
          class="h-4 w-4 animate-spin text-primary-600"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>

        <button
          type="button"
          role="switch"
          :aria-checked="enabled"
          :aria-disabled="!isCommon"
          :aria-describedby="descriptionId"
          :disabled="!isCommon || saving"
          :title="!isCommon ? 'Alerta obrigatório por Portaria 344/98' : (enabled ? 'Desativar alertas' : 'Ativar alertas')"
          class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
          :class="[
            enabled ? 'bg-primary-600' : 'bg-surface-muted',
          ]"
          @click="toggle"
        >
          <span class="sr-only">{{ enabled ? 'Desativar alertas de vencimento' : 'Ativar alertas de vencimento' }}</span>
          <span
            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200"
            :class="enabled ? 'translate-x-5' : 'translate-x-0'"
            aria-hidden="true"
          />
        </button>

        <!-- Label textual do estado -->
        <span class="text-xs font-medium text-foreground-muted w-14 shrink-0">
          {{ saving ? 'Salvando…' : (enabled ? 'Ativado' : 'Desativado') }}
        </span>
      </div>
    </div>

    <!-- Lista de alertas D-15 / D-7 / D-1 -->
    <div class="rounded-lg border border-border bg-surface-elevated overflow-hidden">
      <table class="min-w-full text-xs" aria-label="Cadência de alertas de vencimento">
        <thead>
          <tr class="border-b border-border">
            <th scope="col" class="px-3 py-2 text-left font-semibold text-foreground-muted uppercase tracking-wide">Alerta</th>
            <th scope="col" class="px-3 py-2 text-left font-semibold text-foreground-muted uppercase tracking-wide">Status</th>
            <th scope="col" class="hidden px-3 py-2 text-left font-semibold text-foreground-muted uppercase tracking-wide sm:table-cell">Agendado para</th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading state -->
          <tr v-if="loadingAlerts" aria-busy="true" aria-label="Carregando alertas">
            <td colspan="3" class="px-3 py-3 text-center text-foreground-muted">
              <div class="flex items-center justify-center gap-2">
                <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Carregando alertas...
              </div>
            </td>
          </tr>

          <!-- Linhas de alertas -->
          <tr
            v-for="alert in alertRows"
            v-else
            :key="alert.alert_type"
            class="border-b border-border last:border-0 hover:bg-surface transition"
          >
            <!-- Passo D-15 / D-7 / D-1 -->
            <td class="px-3 py-2.5 font-medium text-foreground">
              {{ ALERT_STEP_LABELS[alert.alert_type] }}
            </td>

            <!-- Status com ícone -->
            <td class="px-3 py-2.5">
              <span
                v-if="alert.status"
                class="inline-flex items-center gap-1"
                :class="STATUS_CLASSES[statusIcon(alert.status)]"
                :title="alert.skip_reason ? `Motivo: ${alert.skip_reason}` : undefined"
              >
                <!-- Ícone check (dispatched) -->
                <svg
                  v-if="statusIcon(alert.status) === 'check'"
                  class="h-3.5 w-3.5 shrink-0"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="2"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>

                <!-- Ícone relógio (pending) -->
                <svg
                  v-else-if="statusIcon(alert.status) === 'pending'"
                  class="h-3.5 w-3.5 shrink-0"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4-8a9 9 0 110 18A9 9 0 0112 3z" />
                </svg>

                <!-- Ícone X (cancelled / skip) -->
                <svg
                  v-else-if="statusIcon(alert.status) === 'skip' || statusIcon(alert.status) === 'cancelled'"
                  class="h-3.5 w-3.5 shrink-0"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>

                <!-- Ícone bloqueado -->
                <svg
                  v-else-if="statusIcon(alert.status) === 'blocked'"
                  class="h-3.5 w-3.5 shrink-0"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>

                {{ statusLabel(alert.status) }}

                <!-- Tooltip skip_reason acessível -->
                <span
                  v-if="alert.skip_reason"
                  class="sr-only"
                  :aria-label="`Motivo: ${alert.skip_reason}`"
                />
              </span>
              <span v-else class="text-foreground-muted">—</span>
            </td>

            <!-- Data agendada -->
            <td class="hidden px-3 py-2.5 text-foreground-muted sm:table-cell">
              <span v-if="alert.dispatched_at" class="text-success-600">
                Enviado
              </span>
              <span v-else-if="alert.scheduled_for">
                {{ fmtDate(alert.scheduled_for) }}
              </span>
              <span v-else>—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Toast acessível (padrão Fase 6) -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-1"
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
        class="mt-2 flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium ring-1"
        :class="[
          toast.type === 'error'
            ? 'bg-danger-50 text-danger-700 ring-danger-200'
            : 'bg-success-50 text-success-700 ring-success-200',
        ]"
      >
        {{ toast.message }}
      </div>
    </Transition>
  </div>
</template>

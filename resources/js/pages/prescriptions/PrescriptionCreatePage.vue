<script setup>
/**
 * T083 (Fase 7) — Formulário de criação de receituário (US-8.1).
 *
 * Features:
 *  - Seleção de paciente (autocomplete PatientAutocomplete reutilizado)
 *  - Radio tipo: Comum / Especial / Controlada + explicação inline
 *  - issued_at (date picker, default hoje)
 *  - Para common: radio duration_days {30, 60, 90, 180} dias
 *  - Para special/controlled: read-only "30 dias (Portaria 344/98)"
 *  - appointment_id opcional
 *  - PrescriptionFormItems (respeitando max e tipo)
 *  - notes (textarea)
 *  - alert_disabled (checkbox — só para common)
 *  - Preview expires_at em tempo real
 *  - Erros 422 por campo + validação client-side
 *  - Redirecionamento para PrescriptionShowPage após sucesso
 */
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { DateTime } from 'luxon'
import { usePrescriptionsStore } from '@/stores/prescriptionsStore'
import api from '@/lib/api'
import PrescriptionFormItems from '@/components/prescriptions/PrescriptionFormItems.vue'

// ─── Toast local (padrão Fase 6) ─────────────────────────────────────────────

const router = useRouter()
const store = usePrescriptionsStore()

const toast = ref(null)
let toastTimer = null

function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => {
    toast.value = null
  }, 5000)
}

// ─── Form state ───────────────────────────────────────────────────────────────

const today = DateTime.now().toFormat('yyyy-MM-dd')

const form = ref({
  patient_id: null,
  patient_label: '',
  type: 'common',
  issued_at: today,
  duration_days: 30,
  appointment_id: null,
  items: [{ medication_name: '', posology: '' }],
  notes: '',
  alert_disabled: false,
})

const fieldErrors = ref({})
const submitting = ref(false)

// ─── Validação ────────────────────────────────────────────────────────────────

function validate() {
  const errors = {}

  if (!form.value.patient_id) errors.patient_id = ['Selecione o paciente.']
  if (!form.value.type) errors.type = ['Selecione o tipo de receita.']
  if (!form.value.issued_at) errors.issued_at = ['Informe a data de emissão.']

  // Items
  if (!form.value.items.length) {
    errors['items'] = ['Adicione pelo menos um medicamento.']
  } else {
    form.value.items.forEach((item, idx) => {
      if (!item.medication_name?.trim()) {
        errors[`items.${idx}.medication_name`] = ['Informe o nome do medicamento.']
      }
      if (!item.posology?.trim()) {
        errors[`items.${idx}.posology`] = ['Informe a posologia.']
      }
    })
  }

  return errors
}

// ─── Preview expires_at ───────────────────────────────────────────────────────

const expiresAtPreview = computed(() => {
  if (!form.value.issued_at) return null
  const base = DateTime.fromISO(form.value.issued_at)
  if (!base.isValid) return null

  const days =
    form.value.type === 'common' ? form.value.duration_days : 30
  return base.plus({ days })
})

const expiresAtDisplay = computed(() => {
  if (!expiresAtPreview.value) return null
  const date = expiresAtPreview.value
  const diff = Math.ceil(date.diff(DateTime.now(), 'days').days)
  return `${date.toFormat('dd/MM/yyyy')} (em ${diff} dia${diff !== 1 ? 's' : ''})`
})

// ─── Busca de paciente ────────────────────────────────────────────────────────

const patientQuery = ref('')
const patientResults = ref([])
const patientSearchLoading = ref(false)
let patientTimer = null

watch(patientQuery, (v) => {
  clearTimeout(patientTimer)
  form.value.patient_id = null
  form.value.patient_label = ''
  if (!v || v.length < 2) {
    patientResults.value = []
    return
  }
  patientTimer = setTimeout(async () => {
    patientSearchLoading.value = true
    try {
      const { data } = await api.get('/pacientes', { params: { search: v, per_page: 8 } })
      patientResults.value = data.data ?? []
    } finally {
      patientSearchLoading.value = false
    }
  }, 250)
})

function selectPatient(p) {
  form.value.patient_id = p.id
  form.value.patient_label = p.nome
  patientQuery.value = p.nome
  patientResults.value = []
}

// ─── Quando muda o tipo — limita items ───────────────────────────────────────

watch(
  () => form.value.type,
  (newType) => {
    if (newType === 'controlled' && form.value.items.length > 1) {
      form.value.items = [form.value.items[0]]
    }
    // Desabilitar alerta não é permitido para special/controlled
    if (newType !== 'common') {
      form.value.alert_disabled = false
    }
  },
)

// ─── Submit ───────────────────────────────────────────────────────────────────

async function submit() {
  if (submitting.value) return

  fieldErrors.value = validate()
  if (Object.keys(fieldErrors.value).length) return

  submitting.value = true
  try {
    const payload = {
      patient_id: form.value.patient_id,
      type: form.value.type,
      issued_at: form.value.issued_at,
      items: form.value.items,
      notes: form.value.notes || null,
    }

    if (form.value.type === 'common') {
      payload.duration_days = form.value.duration_days
      payload.alert_disabled = form.value.alert_disabled
    }

    if (form.value.appointment_id) {
      payload.appointment_id = form.value.appointment_id
    }

    const created = await store.create(payload)
    showToast('Receita criada com sucesso.')
    router.push({ name: 'prescriptions.show', params: { id: created.id } })
  } catch (e) {
    if (e?.response?.status === 422 && e.response.data?.errors) {
      fieldErrors.value = e.response.data.errors
    } else {
      fieldErrors.value = {
        _global: [e?.response?.data?.message ?? 'Erro ao criar receita. Tente novamente.'],
      }
    }
    // Scroll ao topo para mostrar erro global
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    submitting.value = false
  }
}

const TYPE_OPTIONS = [
  {
    value: 'common',
    label: 'Comum',
    description: 'Receita comum — validade configurável de 30 a 180 dias. Aceita até 10 medicamentos.',
    color: 'primary',
  },
  {
    value: 'special',
    label: 'Especial',
    description: 'Receita Especial/Azul (Portaria 344/98, listas B1/B2) — validade fixa de 30 dias. Até 10 medicamentos.',
    color: 'warning',
  },
  {
    value: 'controlled',
    label: 'Controlada',
    description: 'Receita Controlada/Amarela (Portaria 344/98, lista A) — validade fixa de 30 dias. Exatamente 1 medicamento.',
    color: 'danger',
  },
]

const DURATION_PRESETS = [30, 60, 90, 180]
</script>

<template>
  <div class="min-h-screen bg-background">
    <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6">

      <!-- Cabeçalho -->
      <div class="mb-6 flex items-center gap-3">
        <router-link
          :to="{ name: 'prescriptions.index' }"
          class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
          aria-label="Voltar para lista de receituários"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
          </svg>
        </router-link>
        <h1 class="text-xl font-semibold text-foreground">Nova Receita</h1>
      </div>

      <form class="space-y-6" novalidate @submit.prevent="submit">

        <!-- Erro global -->
        <div
          v-if="fieldErrors._global"
          role="alert"
          class="rounded-lg bg-danger-50 border border-danger-200 px-4 py-3 text-sm text-danger-700"
        >
          {{ fieldErrors._global[0] }}
        </div>

        <!-- Paciente -->
        <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
          <label for="form-patient" class="block text-sm font-medium text-foreground">
            Paciente <span class="text-danger-500" aria-hidden="true">*</span>
          </label>
          <div class="relative">
            <input
              id="form-patient"
              v-model="patientQuery"
              type="text"
              autocomplete="off"
              placeholder="Buscar paciente por nome ou CPF..."
              aria-required="true"
              :aria-invalid="!!fieldErrors.patient_id"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              :class="{ 'border-danger-500': fieldErrors.patient_id }"
            />
            <div v-if="patientSearchLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-foreground-muted">...</div>
            <ul
              v-if="patientResults.length"
              class="absolute z-10 mt-1 w-full rounded-lg border border-border bg-surface shadow-lg max-h-48 overflow-auto"
              role="listbox"
              aria-label="Sugestões de pacientes"
            >
              <li
                v-for="p in patientResults"
                :key="p.id"
                role="option"
                class="cursor-pointer px-3 py-2 text-sm text-foreground hover:bg-primary-50"
                @mousedown.prevent="selectPatient(p)"
              >
                {{ p.nome }}
                <span class="ml-1 text-xs text-foreground-muted">{{ p.cpf || p.telefone_primario }}</span>
              </li>
            </ul>
          </div>
          <p v-if="form.patient_id" class="text-xs text-success-700">Paciente selecionado: <strong>{{ form.patient_label }}</strong></p>
          <p v-if="fieldErrors.patient_id" role="alert" class="text-xs text-danger-500">
            {{ fieldErrors.patient_id[0] }}
          </p>
        </div>

        <!-- Tipo de receita -->
        <div class="rounded-xl border border-border bg-surface p-4 space-y-3">
          <p class="text-sm font-medium text-foreground">
            Tipo de receita <span class="text-danger-500" aria-hidden="true">*</span>
          </p>
          <p v-if="fieldErrors.type" role="alert" class="text-xs text-danger-500">
            {{ fieldErrors.type[0] }}
          </p>
          <div class="space-y-2" role="radiogroup" aria-label="Tipo de receita">
            <label
              v-for="opt in TYPE_OPTIONS"
              :key="opt.value"
              class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
              :class="[
                form.type === opt.value
                  ? 'border-primary-400 bg-primary-50'
                  : 'border-border hover:bg-surface-elevated',
              ]"
            >
              <input
                type="radio"
                :value="opt.value"
                v-model="form.type"
                class="mt-0.5 accent-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                :aria-label="opt.label"
              />
              <div>
                <span class="text-sm font-medium text-foreground">{{ opt.label }}</span>
                <p class="text-xs text-foreground-muted mt-0.5">{{ opt.description }}</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Data de emissão -->
        <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
          <label for="form-issued-at" class="block text-sm font-medium text-foreground">
            Data de emissão <span class="text-danger-500" aria-hidden="true">*</span>
          </label>
          <input
            id="form-issued-at"
            v-model="form.issued_at"
            type="date"
            aria-required="true"
            :aria-invalid="!!fieldErrors.issued_at"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            :class="{ 'border-danger-500': fieldErrors.issued_at }"
          />
          <p v-if="fieldErrors.issued_at" role="alert" class="text-xs text-danger-500">
            {{ fieldErrors.issued_at[0] }}
          </p>
        </div>

        <!-- Validade -->
        <div class="rounded-xl border border-border bg-surface p-4 space-y-3">
          <p class="text-sm font-medium text-foreground">Validade</p>

          <!-- Common: presets -->
          <div v-if="form.type === 'common'" role="radiogroup" aria-label="Duração da validade">
            <div class="grid grid-cols-4 gap-2">
              <label
                v-for="days in DURATION_PRESETS"
                :key="days"
                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border py-3 text-sm transition"
                :class="[
                  form.duration_days === days
                    ? 'border-primary-400 bg-primary-50 text-primary-700 font-semibold'
                    : 'border-border text-foreground hover:bg-surface-elevated',
                ]"
              >
                <input
                  type="radio"
                  :value="days"
                  v-model="form.duration_days"
                  class="sr-only"
                  :aria-label="`${days} dias`"
                />
                {{ days }}d
              </label>
            </div>
          </div>

          <!-- Special / Controlled: fixo -->
          <div v-else class="flex items-center gap-2 rounded-lg bg-warning-50 border border-warning-200 px-4 py-3">
            <svg class="h-4 w-4 text-warning-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
            <p class="text-sm text-warning-800">
              Validade fixa: <strong>30 dias</strong> (Portaria 344/98)
            </p>
          </div>

          <!-- Preview expires_at -->
          <p v-if="expiresAtDisplay" class="text-xs text-foreground-muted">
            Esta receita expira em <strong class="text-foreground">{{ expiresAtDisplay }}</strong>
          </p>
        </div>

        <!-- Consulta vinculada (opcional) -->
        <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
          <label for="form-appointment" class="block text-sm font-medium text-foreground">
            Consulta vinculada
            <span class="ml-1 text-xs font-normal text-foreground-muted">(opcional)</span>
          </label>
          <input
            id="form-appointment"
            v-model="form.appointment_id"
            type="number"
            min="1"
            placeholder="ID da consulta (se aplicável)"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
          />
          <p class="text-xs text-foreground-muted">
            Deixe em branco para prescrição independente.
          </p>
        </div>

        <!-- Medicamentos -->
        <div class="rounded-xl border border-border bg-surface p-4">
          <PrescriptionFormItems
            v-model="form.items"
            :type="form.type"
            :errors="fieldErrors"
          />
          <p v-if="fieldErrors.items" role="alert" class="mt-2 text-xs text-danger-500">
            {{ fieldErrors.items[0] }}
          </p>
        </div>

        <!-- Observações -->
        <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
          <label for="form-notes" class="block text-sm font-medium text-foreground">
            Observações
            <span class="ml-1 text-xs font-normal text-foreground-muted">(opcional)</span>
          </label>
          <textarea
            id="form-notes"
            v-model="form.notes"
            rows="3"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 resize-none"
            placeholder="Observações internas sobre a receita..."
          />
        </div>

        <!-- Alerta de vencimento (só para common) -->
        <div
          v-if="form.type === 'common'"
          class="rounded-xl border border-border bg-surface p-4"
        >
          <label class="flex cursor-pointer items-center gap-3">
            <input
              type="checkbox"
              v-model="form.alert_disabled"
              class="h-4 w-4 rounded border-border accent-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            />
            <span class="text-sm text-foreground">
              Desabilitar alertas de vencimento para esta receita
            </span>
          </label>
          <p class="ml-7 mt-1 text-xs text-foreground-muted">
            Para receitas especiais e controladas, alertas são obrigatórios.
          </p>
        </div>

        <!-- Ações -->
        <div class="flex items-center justify-end gap-3 pb-6">
          <router-link
            :to="{ name: 'prescriptions.index' }"
            class="rounded-lg px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
          >
            Cancelar
          </router-link>
          <button
            type="submit"
            :disabled="submitting"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60 transition"
          >
            <svg
              v-if="submitting"
              class="h-4 w-4 animate-spin"
              fill="none"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ submitting ? 'Salvando...' : 'Criar receita' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Toast local (padrão Fase 6) -->
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
</template>

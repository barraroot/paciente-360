<script setup>
/**
 * T143 (Fase 7 Lote D) — Página de renovação de receituário (US-8.3).
 *
 * Fluxo:
 *  1. Carrega a receita original via prescriptionsStore.fetchOne(id).
 *  2. Pré-preenche form com dados da original (patient read-only, type read-only,
 *     issued_at = hoje, duration_days / items / notes / alert_disabled editáveis).
 *  3. Submit → POST /api/v1/prescriptions com renewed_from_id setado.
 *  4. 201: toast + redireciona para prescriptions.show da NOVA receita.
 *  5. 422 prescription_not_eligible_for_renewal: toast erro com reason traduzido.
 *  6. Outros 422: mapeia erros por campo.
 *
 * A11y: foco automático em issued_at ao montar (primeiro campo editável).
 */
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { DateTime } from 'luxon'
import { usePrescriptionsStore } from '@/stores/prescriptionsStore'
import PrescriptionTypeBadge from '@/components/prescriptions/PrescriptionTypeBadge.vue'
import PrescriptionStatusPill from '@/components/prescriptions/PrescriptionStatusPill.vue'
import PrescriptionFormItems from '@/components/prescriptions/PrescriptionFormItems.vue'

const route = useRoute()
const router = useRouter()
const store = usePrescriptionsStore()

const prescriptionId = computed(() => route.params.id)

// ─── Estado local ─────────────────────────────────────────────────────────────

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

// ─── Refs DOM ─────────────────────────────────────────────────────────────────

const issuedAtRef = ref(null)

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref(null)
let toastTimer = null

function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => {
    toast.value = null
  }, 5000)
}

// ─── Tradução de reasons de inelegibilidade ───────────────────────────────────

const REASON_LABELS = {
  prescription_not_active: 'Receita original não está ativa.',
  prescription_already_renewed: 'Esta receita já foi renovada.',
  prescription_outside_renewal_window: 'Receita fora da janela de renovação (mais de 30 dias do vencimento).',
}

function translateReason(reason) {
  return REASON_LABELS[reason] ?? reason ?? 'Receita não elegível para renovação.'
}

// ─── Carregamento da receita original ─────────────────────────────────────────

onMounted(async () => {
  try {
    await store.fetchOne(prescriptionId.value)
    const original = store.current

    if (original) {
      // Pré-preenche campos herdados
      form.value.patient_id = original.patient_id
      form.value.patient_label = original.patient_name ?? `Paciente #${original.patient_id}`
      form.value.type = original.type
      form.value.duration_days = original.duration_days ?? 30
      form.value.notes = original.notes ?? ''
      form.value.alert_disabled = original.alert_disabled ?? false

      // Items da original: mapeados como ponto de partida editável
      if (original.items?.length) {
        form.value.items = original.items.map((it) => ({
          medication_name: it.medication_name ?? '',
          concentration: it.concentration ?? '',
          pharmaceutical_form: it.pharmaceutical_form ?? '',
          posology: it.posology ?? '',
          quantity: it.quantity ?? '',
          treatment_duration: it.treatment_duration ?? '',
        }))
      }

      // Query param ?appointment_id=X
      if (route.query.appointment_id) {
        form.value.appointment_id = Number(route.query.appointment_id)
      }
    }
  } catch {
    // Erro já exposto em store.error
  }

  // Foca no primeiro campo editável após carregar
  await nextTick()
  issuedAtRef.value?.focus()
})

onUnmounted(() => {
  if (toastTimer) clearTimeout(toastTimer)
})

const original = computed(() => store.current)

// ─── Preview expires_at ───────────────────────────────────────────────────────

const expiresAtPreview = computed(() => {
  if (!form.value.issued_at) return null
  const base = DateTime.fromISO(form.value.issued_at)
  if (!base.isValid) return null
  const days = form.value.type === 'common' ? form.value.duration_days : 30
  return base.plus({ days })
})

const expiresAtDisplay = computed(() => {
  if (!expiresAtPreview.value) return null
  const date = expiresAtPreview.value
  const diff = Math.ceil(date.diff(DateTime.now(), 'days').days)
  return `${date.toFormat('dd/MM/yyyy')} (em ${diff} dia${diff !== 1 ? 's' : ''})`
})

// ─── Quando muda o tipo — limita items ───────────────────────────────────────
// (herdado do tipo original: não permite alterar, mas guarda-chuva por segurança)

watch(
  () => form.value.type,
  (newType) => {
    if (newType === 'controlled' && form.value.items.length > 1) {
      form.value.items = [form.value.items[0]]
    }
    if (newType !== 'common') {
      form.value.alert_disabled = false
    }
  },
)

// ─── Validação client-side ────────────────────────────────────────────────────

function validate() {
  const errors = {}

  if (!form.value.issued_at) errors.issued_at = ['Informe a data de emissão.']

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

// ─── Presets duração ─────────────────────────────────────────────────────────

const DURATION_PRESETS = [30, 60, 90, 180]

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
      renewed_from_id: Number(prescriptionId.value),
    }

    if (form.value.type === 'common') {
      payload.duration_days = form.value.duration_days
      payload.alert_disabled = form.value.alert_disabled
    }

    if (form.value.appointment_id) {
      payload.appointment_id = form.value.appointment_id
    }

    const created = await store.create(payload)

    showToast('Receita renovada com sucesso.')
    // Redireciona para a NOVA receita criada
    await router.push({ name: 'prescriptions.show', params: { id: created.id } })
  } catch (e) {
    const status = e?.response?.status
    const responseData = e?.response?.data

    if (status === 422) {
      // Inelegibilidade específica de renovação
      if (responseData?.error === 'prescription_not_eligible_for_renewal') {
        showToast(translateReason(responseData.reason), 'error')
        return
      }
      // Erros por campo genéricos
      if (responseData?.errors) {
        fieldErrors.value = responseData.errors
      } else {
        fieldErrors.value = {
          _global: [responseData?.message ?? 'Erro de validação. Verifique os campos.'],
        }
      }
    } else {
      fieldErrors.value = {
        _global: [responseData?.message ?? 'Erro ao renovar receita. Tente novamente.'],
      }
    }

    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-background">
    <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6">

      <!-- Breadcrumb + cabeçalho -->
      <nav class="mb-1 flex items-center gap-1 text-xs text-foreground-muted" aria-label="Breadcrumb">
        <router-link
          :to="{ name: 'prescriptions.index' }"
          class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
        >
          Receituários
        </router-link>
        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
        <router-link
          v-if="prescriptionId"
          :to="{ name: 'prescriptions.show', params: { id: prescriptionId } }"
          class="hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
        >
          Receita #{{ prescriptionId }}
        </router-link>
        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-foreground" aria-current="page">Renovar</span>
      </nav>

      <div class="mb-6 flex items-center gap-3">
        <router-link
          :to="{ name: 'prescriptions.show', params: { id: prescriptionId } }"
          class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
          aria-label="Voltar para detalhe da receita"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
          </svg>
        </router-link>
        <h1 class="text-xl font-semibold text-foreground">
          Renovar Receita #{{ prescriptionId }}
        </h1>
      </div>

      <!-- Loading skeleton -->
      <div v-if="store.loading && !original" aria-busy="true" aria-label="Carregando receita original" class="space-y-4">
        <div class="h-24 rounded-xl bg-surface-elevated animate-pulse" />
        <div class="h-32 rounded-xl bg-surface-elevated animate-pulse" />
        <div class="h-48 rounded-xl bg-surface-elevated animate-pulse" />
      </div>

      <!-- Erro de carregamento -->
      <div
        v-else-if="store.error && !original"
        class="rounded-xl border border-border bg-surface p-8 text-center"
        role="alert"
      >
        <p class="text-sm text-danger-600 mb-3">{{ store.error }}</p>
        <button
          type="button"
          class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
          @click="store.fetchOne(prescriptionId)"
        >
          Tentar novamente
        </button>
      </div>

      <template v-else-if="original || !store.loading">

        <!-- Banner informativo -->
        <div
          class="mb-6 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3"
          role="note"
          aria-label="Informação sobre renovação"
        >
          <svg class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
          </svg>
          <p class="text-sm text-blue-800">
            Esta renovação criará uma nova receita vinculada à original. A receita original será marcada como <strong>'superseded'</strong> automaticamente após a criação.
          </p>
        </div>

        <!-- Cabeçalho resumo da original -->
        <div v-if="original" class="mb-6 rounded-xl border border-border bg-surface p-4 flex flex-wrap items-center gap-2">
          <span class="text-xs font-medium text-foreground-muted">Receita original:</span>
          <PrescriptionTypeBadge :type="original.type" />
          <PrescriptionStatusPill :status="original.status" />
          <span class="text-xs text-foreground-muted">
            Emitida em {{ original.issued_at ? DateTime.fromISO(original.issued_at).toFormat('dd/MM/yyyy') : '—' }}
          </span>
        </div>

        <form class="space-y-6" novalidate @submit.prevent="submit">

          <!-- Erro global -->
          <div
            v-if="fieldErrors._global"
            role="alert"
            aria-live="assertive"
            class="rounded-lg bg-danger-50 border border-danger-200 px-4 py-3 text-sm text-danger-700"
          >
            {{ fieldErrors._global[0] }}
          </div>

          <!-- Paciente (read-only — herdado da original) -->
          <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
            <p class="text-sm font-medium text-foreground">Paciente</p>
            <div class="flex items-center gap-2 rounded-lg border border-border bg-surface-elevated px-3 py-2">
              <svg class="h-4 w-4 text-foreground-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
              </svg>
              <span class="text-sm text-foreground">{{ form.patient_label }}</span>
              <span class="ml-auto text-xs text-foreground-muted">(herdado — não editável)</span>
            </div>
          </div>

          <!-- Tipo (read-only — herdado da original) -->
          <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
            <p class="text-sm font-medium text-foreground">Tipo de receita</p>
            <div class="flex items-center gap-2 rounded-lg border border-border bg-surface-elevated px-3 py-2">
              <PrescriptionTypeBadge v-if="form.type" :type="form.type" />
              <span class="ml-auto text-xs text-foreground-muted">(herdado — não editável)</span>
            </div>
          </div>

          <!-- Data de emissão (editável — default hoje) -->
          <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
            <label for="renew-issued-at" class="block text-sm font-medium text-foreground">
              Data de emissão <span class="text-danger-500" aria-hidden="true">*</span>
            </label>
            <input
              id="renew-issued-at"
              ref="issuedAtRef"
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

            <!-- Common: presets editáveis -->
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
              Nova receita expira em <strong class="text-foreground">{{ expiresAtDisplay }}</strong>
            </p>
          </div>

          <!-- Consulta vinculada (opcional) -->
          <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
            <label for="renew-appointment" class="block text-sm font-medium text-foreground">
              Consulta vinculada
              <span class="ml-1 text-xs font-normal text-foreground-muted">(opcional)</span>
            </label>
            <input
              id="renew-appointment"
              v-model="form.appointment_id"
              type="number"
              min="1"
              placeholder="ID da consulta (se aplicável)"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            />
            <p class="text-xs text-foreground-muted">
              Deixe em branco para renovação independente.
            </p>
          </div>

          <!-- Medicamentos (editável — pré-preenchido com original) -->
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

          <!-- Observações (editável — pré-preenchido com original) -->
          <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
            <label for="renew-notes" class="block text-sm font-medium text-foreground">
              Observações
              <span class="ml-1 text-xs font-normal text-foreground-muted">(opcional)</span>
            </label>
            <textarea
              id="renew-notes"
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
              :to="{ name: 'prescriptions.show', params: { id: prescriptionId } }"
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
              {{ submitting ? 'Renovando...' : 'Renovar receita' }}
            </button>
          </div>
        </form>
      </template>

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
  </div>
</template>

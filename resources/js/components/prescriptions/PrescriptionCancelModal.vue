<script setup>
/**
 * T081 (Fase 7) — Modal de cancelamento de receita (US-8.1 / AC-8.1.8 / Q3).
 *
 * Padrão a11y Fase 6:
 *  - Teleport to="body" + role=dialog + aria-modal + aria-labelledby
 *  - Focus trap Tab/Shift+Tab
 *  - @keydown.esc.prevent="close"
 *  - Overlay click fecha
 *  - bottom-sheet mobile (items-end sm:items-center)
 *
 * Categorias: erro_emissao, desistencia_paciente, substituicao, outro.
 * Cancellation reason: textarea máx 500 chars + contador visível.
 * Warning irreversível exibido antes do submit.
 */
import { ref, watch, nextTick } from 'vue'
import { usePrescriptionsStore } from '@/stores/prescriptionsStore'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  prescription: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'cancelled'])

const store = usePrescriptionsStore()

// ─── Estado do form ───────────────────────────────────────────────────────────

const form = ref({
  category: '',
  reason: '',
})

const fieldErrors = ref({})
const submitting = ref(false)
const dialogEl = ref(null)

const CATEGORIES = [
  { value: '', label: 'Selecione uma categoria...' },
  { value: 'erro_emissao', label: 'Erro de emissão' },
  { value: 'desistencia_paciente', label: 'Desistência do paciente' },
  { value: 'substituicao', label: 'Substituição por nova receita' },
  { value: 'outro', label: 'Outro' },
]

const REASON_MAX = 500

// ─── Abertura / Fechamento ─────────────────────────────────────────────────────

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      form.value = { category: '', reason: '' }
      fieldErrors.value = {}
      submitting.value = false
      await nextTick()
      dialogEl.value?.querySelector('select')?.focus()
    }
  },
)

function close() {
  emit('update:modelValue', false)
}

function handleOverlayClick(e) {
  if (e.target === e.currentTarget) close()
}

// ─── Focus trap ───────────────────────────────────────────────────────────────

function getFocusable() {
  if (!dialogEl.value) return []
  return Array.from(
    dialogEl.value.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  )
}

function trapFocus(e) {
  const focusable = getFocusable()
  if (!focusable.length) return
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (e.key === 'Tab') {
    if (e.shiftKey) {
      if (document.activeElement === first) {
        e.preventDefault()
        last.focus()
      }
    } else {
      if (document.activeElement === last) {
        e.preventDefault()
        first.focus()
      }
    }
  }
}

// ─── Submit ───────────────────────────────────────────────────────────────────

async function submit() {
  fieldErrors.value = {}

  // Validação client-side
  const errors = {}
  if (!form.value.category) errors.category = ['Selecione uma categoria.']
  if (!form.value.reason || form.value.reason.trim().length < 5) {
    errors.reason = ['Informe o motivo do cancelamento (mínimo 5 caracteres).']
  }
  if (Object.keys(errors).length) {
    fieldErrors.value = errors
    return
  }

  submitting.value = true
  try {
    await store.cancel(props.prescription.id, {
      category: form.value.category,
      reason: form.value.reason,
    })
    emit('cancelled', store.current)
    close()
  } catch (e) {
    if (e?.response?.status === 422 && e.response.data?.errors) {
      fieldErrors.value = e.response.data.errors
    } else {
      fieldErrors.value = {
        _global: [e?.response?.data?.message ?? 'Erro ao cancelar receita. Tente novamente.'],
      }
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
      @click="handleOverlayClick"
      @keydown.esc.prevent="close"
      @keydown="trapFocus"
    >
      <div
        ref="dialogEl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cancel-modal-title"
        class="w-full rounded-t-2xl bg-surface shadow-xl sm:max-w-md sm:rounded-xl"
      >
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
          <h2 id="cancel-modal-title" class="text-base font-semibold text-foreground">
            Cancelar receita
          </h2>
          <button
            type="button"
            class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
            aria-label="Fechar modal"
            @click="close"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Corpo -->
        <form class="px-6 py-4 space-y-4" novalidate @submit.prevent="submit">

          <!-- Warning irreversível (AC-8.1.8) -->
          <div class="flex items-start gap-3 rounded-lg bg-danger-50 border border-danger-200 px-4 py-3">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-danger-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p class="text-sm text-danger-700">
              Esta ação é <strong>irreversível</strong>. A receita ficará indisponível para uso.
            </p>
          </div>

          <!-- Erro global -->
          <p
            v-if="fieldErrors._global"
            role="alert"
            class="text-sm text-danger-600"
          >
            {{ fieldErrors._global[0] }}
          </p>

          <!-- Categoria -->
          <div>
            <label for="cancel-category" class="block text-sm font-medium text-foreground mb-1">
              Motivo <span class="text-danger-600" aria-hidden="true">*</span>
            </label>
            <select
              id="cancel-category"
              v-model="form.category"
              aria-required="true"
              :aria-invalid="!!fieldErrors.category"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              :class="{ 'border-danger-500': fieldErrors.category }"
            >
              <option
                v-for="cat in CATEGORIES"
                :key="cat.value"
                :value="cat.value"
              >
                {{ cat.label }}
              </option>
            </select>
            <p
              v-if="fieldErrors.category"
              role="alert"
              class="mt-1 text-xs text-danger-600"
            >
              {{ fieldErrors.category[0] }}
            </p>
          </div>

          <!-- Motivo livre -->
          <div>
            <label for="cancel-reason" class="block text-sm font-medium text-foreground mb-1">
              Descrição <span class="text-danger-600" aria-hidden="true">*</span>
            </label>
            <textarea
              id="cancel-reason"
              v-model="form.reason"
              rows="3"
              :maxlength="REASON_MAX"
              aria-required="true"
              :aria-invalid="!!fieldErrors.reason"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 resize-none"
              :class="{ 'border-danger-500': fieldErrors.reason }"
              placeholder="Descreva o motivo..."
            />
            <div class="flex justify-between mt-1">
              <p
                v-if="fieldErrors.reason"
                role="alert"
                class="text-xs text-danger-600"
              >
                {{ fieldErrors.reason[0] }}
              </p>
              <span class="ml-auto text-xs text-foreground-muted">
                {{ form.reason.length }}/{{ REASON_MAX }}
              </span>
            </div>
          </div>

          <!-- Ações -->
          <div class="flex justify-end gap-3 pt-2">
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
              @click="close"
            >
              Voltar
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="inline-flex items-center gap-2 rounded-lg bg-danger-600 px-4 py-2 text-sm font-medium text-white hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 disabled:opacity-60 transition"
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
              Cancelar receita
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
/**
 * T048 — Componente de formulário para bloqueio de agenda (US-6.1).
 *
 * Refinamentos UX (auditoria 2026-05-13 — Lote B):
 *  - Labels acessíveis com for/id em todos os campos (P0)
 *  - Validação local: ends_at deve ser > starts_at (P0)
 *  - Erros 422 aceitos via prop fieldErrors (P0)
 *  - Loading state no botão submit (P0)
 *  - Botão Cancelar para fechar modal (P1)
 *  - Interface: emit submit({ data, fieldErrors: fn }) + emit cancel (compatível ScheduleConfigPage)
 */
import { ref, watch } from 'vue'

const props = defineProps({
  submitting: { type: Boolean, default: false },
  fieldErrors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['submit', 'cancel'])

// ─── Estado interno ────────────────────────────────────────────────────────────

const form = ref({ starts_at: '', ends_at: '', reason: '' })
const localErrors = ref({})

// Sincroniza erros externos (422 do parent) com os locais
watch(() => props.fieldErrors, (v) => {
  localErrors.value = { ...v }
}, { deep: true })

function clearError(field) {
  if (localErrors.value[field]) {
    const updated = { ...localErrors.value }
    delete updated[field]
    localErrors.value = updated
  }
}

// ─── Validação local ──────────────────────────────────────────────────────────

function validate() {
  const errors = {}
  if (!form.value.starts_at) errors.starts_at = ['A data de início é obrigatória.']
  if (!form.value.ends_at) errors.ends_at = ['A data de fim é obrigatória.']
  if (form.value.starts_at && form.value.ends_at) {
    if (new Date(form.value.ends_at) <= new Date(form.value.starts_at)) {
      errors.ends_at = ['O fim deve ser depois do início.']
    }
  }
  return errors
}

// ─── Submit ────────────────────────────────────────────────────────────────────

function submit() {
  const validationErrors = validate()
  if (Object.keys(validationErrors).length) {
    localErrors.value = validationErrors
    return
  }
  localErrors.value = {}
  emit('submit', {
    data: { ...form.value },
    fieldErrors: (errors) => {
      localErrors.value = errors
    },
  })
}

function reset() {
  form.value = { starts_at: '', ends_at: '', reason: '' }
  localErrors.value = {}
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <!-- Início -->
    <div>
      <label for="sef-starts-at" class="block text-sm font-medium text-foreground mb-1">
        Início do bloqueio <span class="text-danger-500" aria-hidden="true">*</span>
      </label>
      <input
        id="sef-starts-at"
        v-model="form.starts_at"
        type="datetime-local"
        required
        class="w-full rounded-lg border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        :class="localErrors.starts_at ? 'border-danger-500' : 'border-border'"
        @input="clearError('starts_at')"
      />
      <p v-if="localErrors.starts_at" role="alert" class="mt-1 text-xs text-danger-500">
        {{ Array.isArray(localErrors.starts_at) ? localErrors.starts_at[0] : localErrors.starts_at }}
      </p>
    </div>

    <!-- Fim -->
    <div>
      <label for="sef-ends-at" class="block text-sm font-medium text-foreground mb-1">
        Fim do bloqueio <span class="text-danger-500" aria-hidden="true">*</span>
      </label>
      <input
        id="sef-ends-at"
        v-model="form.ends_at"
        type="datetime-local"
        required
        :min="form.starts_at || undefined"
        class="w-full rounded-lg border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        :class="localErrors.ends_at ? 'border-danger-500' : 'border-border'"
        @input="clearError('ends_at')"
      />
      <p v-if="localErrors.ends_at" role="alert" class="mt-1 text-xs text-danger-500">
        {{ Array.isArray(localErrors.ends_at) ? localErrors.ends_at[0] : localErrors.ends_at }}
      </p>
    </div>

    <!-- Motivo -->
    <div>
      <label for="sef-reason" class="block text-sm font-medium text-foreground mb-1">
        Motivo
      </label>
      <input
        id="sef-reason"
        v-model="form.reason"
        type="text"
        placeholder="Ex.: Férias, Congresso médico, Feriado"
        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        @input="clearError('reason')"
      />
      <p v-if="localErrors.reason" role="alert" class="mt-1 text-xs text-danger-500">
        {{ Array.isArray(localErrors.reason) ? localErrors.reason[0] : localErrors.reason }}
      </p>
    </div>

    <!-- Ações -->
    <div class="flex items-center justify-end gap-3 pt-2 border-t border-border">
      <button
        type="button"
        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        @click="$emit('cancel')"
      >
        Cancelar
      </button>
      <button
        type="submit"
        :disabled="submitting"
        class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
      >
        <span v-if="submitting">Adicionando...</span>
        <span v-else>Adicionar bloqueio</span>
      </button>
    </div>
  </form>
</template>

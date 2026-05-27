<script setup>
/**
 * T079 (Fase 7) — Lista dinâmica de medicamentos em um receituário (US-8.1 / Q2).
 *
 * Regras:
 *  - type=controlled → fixo em 1 item (Portaria 344/98); add/remove desabilitados.
 *  - type=common|special → 1-10 itens.
 *  - Autocomplete de nome via GET /api/v1/prescriptions/medications/autocomplete?q=
 *    (se o endpoint não existir, sugestões são mostradas localmente — TODO comentado).
 *  - A11y: labels com for, aria-required, aria-invalid em erros; foco no 1° campo do item adicionado.
 *
 * TODO US-8.1 autocomplete: endpoint GET /api/v1/prescriptions/medications/autocomplete
 * ainda não exposto pelo backend — substituir fetch abaixo quando disponível.
 */
import { ref, computed, watch, nextTick } from 'vue'
import { autocompleteMedications } from '@/lib/prescriptionsApi'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  type: {
    type: String,
    default: 'common',
    validator: (v) => ['common', 'special', 'controlled'].includes(v),
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue'])

// ─── Max de itens ─────────────────────────────────────────────────────────────

const maxItems = computed(() => (props.type === 'controlled' ? 1 : 10))
const isControlled = computed(() => props.type === 'controlled')

// ─── Items internos ───────────────────────────────────────────────────────────

function emptyItem() {
  return {
    _key: Math.random().toString(36).slice(2),
    medication_name: '',
    concentration: '',
    pharmaceutical_form: '',
    posology: '',
    quantity: '',
    treatment_duration: '',
  }
}

const items = ref(
  props.modelValue.length
    ? props.modelValue.map((it) => ({ _key: Math.random().toString(36).slice(2), ...it }))
    : [emptyItem()],
)

// Sincroniza com modelValue externo (ex.: reset de form)
watch(
  () => props.modelValue,
  (v) => {
    if (JSON.stringify(v) !== JSON.stringify(items.value.map(({ _key, ...rest }) => rest))) {
      items.value = v.length
        ? v.map((it) => ({ _key: Math.random().toString(36).slice(2), ...it }))
        : [emptyItem()]
    }
  },
)

// Emite para o pai sempre que items mudar
watch(
  items,
  (v) => {
    emit(
      'update:modelValue',
      v.map(({ _key, ...rest }) => rest),
    )
  },
  { deep: true },
)

// ─── Refs de DOM para foco pós-add ────────────────────────────────────────────

const itemRefs = ref([])

function setItemRef(el, idx) {
  itemRefs.value[idx] = el
}

// ─── Add / Remove ─────────────────────────────────────────────────────────────

function addItem() {
  if (items.value.length >= maxItems.value) return
  items.value.push(emptyItem())
  nextTick(() => {
    const newIdx = items.value.length - 1
    const container = itemRefs.value[newIdx]
    container?.querySelector('input')?.focus()
  })
}

function removeItem(idx) {
  if (items.value.length <= 1) return
  items.value.splice(idx, 1)
}

// ─── Autocomplete de medicamentos ─────────────────────────────────────────────

const autocompleteResults = ref([])
const autocompleteIdx = ref(null) // qual item está com autocomplete aberto
let autocompleteTimer = null

async function onMedicationInput(value, idx) {
  items.value[idx].medication_name = value
  clearTimeout(autocompleteTimer)

  if (!value || value.length < 2) {
    autocompleteResults.value = []
    autocompleteIdx.value = null
    return
  }

  autocompleteTimer = setTimeout(async () => {
    try {
      // TODO US-8.1: endpoint GET /api/v1/prescriptions/medications/autocomplete
      // pode retornar 404 se backend ainda não expôs — silencia o erro.
      const { data } = await autocompleteMedications(value)
      autocompleteResults.value = data.data ?? []
      autocompleteIdx.value = idx
    } catch {
      // Endpoint ainda não implementado — não bloquear UX
      autocompleteResults.value = []
    }
  }, 250)
}

function pickMedication(name, idx) {
  items.value[idx].medication_name = name
  autocompleteResults.value = []
  autocompleteIdx.value = null
}

function closeAutocomplete() {
  autocompleteResults.value = []
  autocompleteIdx.value = null
}

// ─── Erros por índice ─────────────────────────────────────────────────────────

function fieldError(idx, field) {
  return props.errors?.[`items.${idx}.${field}`]?.[0] ?? null
}
</script>

<template>
  <div class="space-y-4">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-medium text-foreground">
        Medicamentos
        <span class="ml-1 text-xs text-foreground-muted">({{ items.length }}/{{ maxItems }})</span>
      </h3>
      <button
        v-if="!isControlled"
        type="button"
        :disabled="items.length >= maxItems"
        class="inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-xs font-medium text-primary-700 ring-1 ring-primary-300 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-40 transition"
        :aria-label="`Adicionar medicamento (${items.length} de ${maxItems})`"
        @click="addItem"
      >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Adicionar
      </button>
    </div>

    <!-- Aviso tipo controlada -->
    <p v-if="isControlled" class="text-xs text-foreground-muted">
      Receita controlada (Portaria 344/98): apenas 1 medicamento por receita.
    </p>

    <!-- Lista de itens -->
    <div
      v-for="(item, idx) in items"
      :key="item._key"
      :ref="(el) => setItemRef(el, idx)"
      class="rounded-xl border border-border bg-surface-elevated p-4 space-y-3 relative"
    >
      <!-- Cabeçalho do item -->
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-foreground-muted uppercase tracking-wide">
          Medicamento {{ idx + 1 }}
        </span>
        <button
          v-if="!isControlled && items.length > 1"
          type="button"
          class="rounded p-1 text-foreground-muted hover:text-danger-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
          :aria-label="`Remover medicamento ${idx + 1}`"
          @click="removeItem(idx)"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Nome do medicamento com autocomplete -->
      <div class="relative">
        <label
          :for="`med-name-${idx}`"
          class="block text-xs font-medium text-foreground mb-1"
        >
          Nome do medicamento
          <span class="text-danger-600" aria-hidden="true">*</span>
        </label>
        <input
          :id="`med-name-${idx}`"
          v-model="item.medication_name"
          type="text"
          autocomplete="off"
          aria-required="true"
          :aria-invalid="!!fieldError(idx, 'medication_name')"
          :aria-describedby="fieldError(idx, 'medication_name') ? `med-name-err-${idx}` : undefined"
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
          :class="{ 'border-danger-500': fieldError(idx, 'medication_name') }"
          placeholder="Ex.: Amoxicilina"
          @input="onMedicationInput($event.target.value, idx)"
          @blur="closeAutocomplete"
        />
        <!-- Autocomplete dropdown -->
        <ul
          v-if="autocompleteIdx === idx && autocompleteResults.length"
          class="absolute z-20 mt-1 w-full rounded-lg border border-border bg-surface shadow-lg max-h-48 overflow-auto"
          role="listbox"
          :aria-label="`Sugestões para ${item.medication_name}`"
        >
          <li
            v-for="suggestion in autocompleteResults"
            :key="suggestion"
            role="option"
            class="cursor-pointer px-3 py-2 text-sm text-foreground hover:bg-primary-50 focus:bg-primary-50"
            @mousedown.prevent="pickMedication(suggestion, idx)"
          >
            {{ suggestion }}
          </li>
        </ul>
        <p
          v-if="fieldError(idx, 'medication_name')"
          :id="`med-name-err-${idx}`"
          role="alert"
          class="mt-1 text-xs text-danger-600"
        >
          {{ fieldError(idx, 'medication_name') }}
        </p>
      </div>

      <!-- Concentração + Forma farmacêutica -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label :for="`med-conc-${idx}`" class="block text-xs font-medium text-foreground mb-1">
            Concentração
          </label>
          <input
            :id="`med-conc-${idx}`"
            v-model="item.concentration"
            type="text"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            placeholder="Ex.: 500mg"
          />
        </div>
        <div>
          <label :for="`med-form-${idx}`" class="block text-xs font-medium text-foreground mb-1">
            Forma farmacêutica
          </label>
          <input
            :id="`med-form-${idx}`"
            v-model="item.pharmaceutical_form"
            type="text"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            placeholder="Ex.: Comprimido"
          />
        </div>
      </div>

      <!-- Posologia -->
      <div>
        <label :for="`med-posology-${idx}`" class="block text-xs font-medium text-foreground mb-1">
          Posologia
          <span class="text-danger-600" aria-hidden="true">*</span>
        </label>
        <textarea
          :id="`med-posology-${idx}`"
          v-model="item.posology"
          rows="2"
          aria-required="true"
          :aria-invalid="!!fieldError(idx, 'posology')"
          :aria-describedby="fieldError(idx, 'posology') ? `med-posology-err-${idx}` : undefined"
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 resize-none"
          :class="{ 'border-danger-500': fieldError(idx, 'posology') }"
          placeholder="Ex.: 1 comprimido a cada 8 horas por 7 dias"
        />
        <p
          v-if="fieldError(idx, 'posology')"
          :id="`med-posology-err-${idx}`"
          role="alert"
          class="mt-1 text-xs text-danger-600"
        >
          {{ fieldError(idx, 'posology') }}
        </p>
      </div>

      <!-- Quantidade + Duração do tratamento -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label :for="`med-qty-${idx}`" class="block text-xs font-medium text-foreground mb-1">
            Quantidade
          </label>
          <input
            :id="`med-qty-${idx}`"
            v-model="item.quantity"
            type="text"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            placeholder="Ex.: 21 comprimidos"
          />
        </div>
        <div>
          <label :for="`med-duration-${idx}`" class="block text-xs font-medium text-foreground mb-1">
            Duração do tratamento
          </label>
          <input
            :id="`med-duration-${idx}`"
            v-model="item.treatment_duration"
            type="text"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            placeholder="Ex.: 7 dias"
          />
        </div>
      </div>
    </div>
  </div>
</template>

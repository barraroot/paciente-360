<script setup>
/**
 * T059 — Componente de formulário para criar/editar tipo de atendimento (US-6.2).
 *
 * Refinamentos UX (auditoria 2026-05-13 — Lote A):
 *  - Labels acessíveis com for/id em todos os campos (P0)
 *  - Erros 422 mapeados por campo em fieldErrors via setErrors() (P0)
 *  - Color picker com label visível + preview da cor selecionada (P1)
 *  - Valor particular formatado ao sair (blur) — edição numérica livre (P1)
 *  - Validação local: nome obrigatório, duração 5-600 (P0)
 *  - Loading state no botão submit (P0)
 *  - Botão Cancelar para fechar modal (P1)
 */
import { ref, watch } from 'vue'

const props = defineProps({
  initialValue: { type: Object, default: null },
})

const emit = defineEmits(['submit', 'cancel'])

// Exposto para o parent injetar erros 422
defineExpose({ setErrors })

// ─── Estado interno ────────────────────────────────────────────────────────────

const defaultForm = () => ({
  nome: '',
  duration_minutes: 30,
  buffer_minutes: 5,
  valor_particular: 0,
  valor_convenio_default: null,
  min_cancellation_hours: null,
  cor: '#3B82F6',
  descricao: '',
  intent_ia: '',
})

const form = ref(defaultForm())
const fieldErrors = ref({})
const submitting = ref(false)

watch(
  () => props.initialValue,
  (v) => {
    form.value = v ? { ...defaultForm(), ...v } : defaultForm()
    fieldErrors.value = {}
  },
  { immediate: true }
)

// ─── Validação local ──────────────────────────────────────────────────────────

function validate() {
  const errors = {}
  if (!form.value.nome?.trim()) errors.nome = ['O nome é obrigatório.']
  if (!form.value.duration_minutes || form.value.duration_minutes < 5) {
    errors.duration_minutes = ['Duração mínima de 5 minutos.']
  }
  if (form.value.duration_minutes > 600) {
    errors.duration_minutes = ['Duração máxima de 600 minutos.']
  }
  if (form.value.valor_particular < 0) {
    errors.valor_particular = ['Valor não pode ser negativo.']
  }
  return errors
}

function clearError(field) {
  if (fieldErrors.value[field]) {
    delete fieldErrors.value[field]
  }
}

// Chamado pelo parent quando API retorna 422
function setErrors(errors) {
  fieldErrors.value = errors
}

// ─── Submit ────────────────────────────────────────────────────────────────────

async function submit() {
  if (submitting.value) return
  const localErrors = validate()
  if (Object.keys(localErrors).length) {
    fieldErrors.value = localErrors
    return
  }
  submitting.value = true
  fieldErrors.value = {}
  // Passa os dados e um callback para que o parent injete erros 422
  emit('submit', {
    data: { ...form.value },
    fieldErrors: (errors) => {
      fieldErrors.value = errors
      submitting.value = false
    },
  })
  // O parent chama fieldErrors() em caso de erro ou fecha o modal em sucesso
  // Aguardamos resolução assíncrona — reset de submitting é feito pelo parent
  // ou pelo fieldErrors callback acima
  setTimeout(() => { submitting.value = false }, 10000) // Segurança
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <!-- Nome -->
    <div>
      <label for="atf-nome" class="block text-sm font-medium text-foreground mb-1">
        Nome <span class="text-danger-600" aria-hidden="true">*</span>
      </label>
      <input
        id="atf-nome"
        v-model="form.nome"
        type="text"
        placeholder="Ex.: Consulta inicial, Retorno, Exame"
        required
        class="w-full rounded-lg border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        :class="fieldErrors.nome ? 'border-danger-500' : 'border-border'"
        @input="clearError('nome')"
      />
      <p v-if="fieldErrors.nome" role="alert" class="mt-1 text-xs text-danger-600">
        {{ Array.isArray(fieldErrors.nome) ? fieldErrors.nome[0] : fieldErrors.nome }}
      </p>
    </div>

    <!-- Duração + Buffer -->
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label for="atf-duration" class="block text-sm font-medium text-foreground mb-1">
          Duração (min) <span class="text-danger-600" aria-hidden="true">*</span>
        </label>
        <input
          id="atf-duration"
          v-model.number="form.duration_minutes"
          type="number"
          min="5"
          max="600"
          class="w-full rounded-lg border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
          :class="fieldErrors.duration_minutes ? 'border-danger-500' : 'border-border'"
          @input="clearError('duration_minutes')"
        />
        <p v-if="fieldErrors.duration_minutes" role="alert" class="mt-1 text-xs text-danger-600">
          {{ Array.isArray(fieldErrors.duration_minutes) ? fieldErrors.duration_minutes[0] : fieldErrors.duration_minutes }}
        </p>
      </div>
      <div>
        <label for="atf-buffer" class="block text-sm font-medium text-foreground mb-1">
          Buffer (min)
          <span class="text-foreground-subtle text-xs font-normal" title="Tempo de descanso após a consulta — não alocado para novos agendamentos">(intervalo)</span>
        </label>
        <input
          id="atf-buffer"
          v-model.number="form.buffer_minutes"
          type="number"
          min="0"
          max="120"
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        />
      </div>
    </div>

    <!-- Valor particular + Valor convênio -->
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label for="atf-valor-particular" class="block text-sm font-medium text-foreground mb-1">
          Valor particular (R$)
        </label>
        <input
          id="atf-valor-particular"
          v-model.number="form.valor_particular"
          type="number"
          step="0.01"
          min="0"
          class="w-full rounded-lg border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
          :class="fieldErrors.valor_particular ? 'border-danger-500' : 'border-border'"
          @input="clearError('valor_particular')"
        />
        <p v-if="fieldErrors.valor_particular" role="alert" class="mt-1 text-xs text-danger-600">
          {{ Array.isArray(fieldErrors.valor_particular) ? fieldErrors.valor_particular[0] : fieldErrors.valor_particular }}
        </p>
      </div>
      <div>
        <label for="atf-valor-convenio" class="block text-sm font-medium text-foreground mb-1">
          Valor convênio (R$)
        </label>
        <input
          id="atf-valor-convenio"
          v-model.number="form.valor_convenio_default"
          type="number"
          step="0.01"
          min="0"
          placeholder="Opcional"
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        />
      </div>
    </div>

    <!-- Cancelamento mínimo -->
    <div>
      <label for="atf-cancel-hours" class="block text-sm font-medium text-foreground mb-1">
        Antecedência mínima para cancelamento (horas)
        <span class="text-foreground-subtle text-xs font-normal">(override da clínica)</span>
      </label>
      <input
        id="atf-cancel-hours"
        v-model.number="form.min_cancellation_hours"
        type="number"
        min="0"
        placeholder="Deixe vazio para usar o padrão da clínica"
        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
      />
    </div>

    <!-- Cor identificadora -->
    <div>
      <label class="block text-sm font-medium text-foreground mb-1" for="atf-cor">
        Cor identificadora
      </label>
      <div class="flex items-center gap-3">
        <input
          id="atf-cor"
          v-model="form.cor"
          type="color"
          class="h-10 w-16 cursor-pointer rounded-lg border border-border bg-surface p-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
          aria-label="Selecionar cor do tipo de atendimento"
        />
        <div class="flex items-center gap-2 text-sm text-foreground-muted">
          <span
            class="h-4 w-4 rounded-full border border-border shrink-0"
            :style="{ backgroundColor: form.cor }"
            aria-hidden="true"
          />
          <code class="font-mono text-xs">{{ form.cor }}</code>
          <span class="text-xs">— aparece no calendário e na lista</span>
        </div>
      </div>
    </div>

    <!-- Descrição -->
    <div>
      <label for="atf-descricao" class="block text-sm font-medium text-foreground mb-1">
        Descrição
      </label>
      <textarea
        id="atf-descricao"
        v-model="form.descricao"
        rows="2"
        placeholder="Descrição interna do tipo de atendimento (opcional)"
        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 resize-none transition"
      />
    </div>

    <!-- Intent IA (futura) -->
    <div>
      <label for="atf-intent-ia" class="block text-sm font-medium text-foreground mb-1">
        Intenção para IA
        <span class="text-foreground-subtle text-xs font-normal">(Fase 6 — classificação automática)</span>
      </label>
      <input
        id="atf-intent-ia"
        v-model="form.intent_ia"
        type="text"
        placeholder="Ex.: consulta_inicial, retorno_pos_exame"
        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
      />
    </div>

    <!-- Erro geral -->
    <p v-if="fieldErrors._general" role="alert" class="text-sm text-danger-600">
      {{ fieldErrors._general }}
    </p>

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
        <span v-if="submitting">Salvando...</span>
        <span v-else>{{ props.initialValue?.id ? 'Salvar alterações' : 'Criar tipo' }}</span>
      </button>
    </div>
  </form>
</template>

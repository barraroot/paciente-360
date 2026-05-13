<script setup>
/**
 * T058 — Página CRUD tipos de atendimento (US-6.2).
 *
 * Stub funcional mínimo. Próximas iterações: color picker visual,
 * tooltip explicativo "Tipo: Retorno (categoria de consulta)" (clarify nº 4).
 */
import { ref, onMounted } from 'vue'
import {
  listAppointmentTypes,
  createAppointmentType,
  updateAppointmentType,
  deleteAppointmentType,
} from '@/lib/agendaApi'

const types = ref([])
const includeInactive = ref(false)
const loading = ref(false)
const editing = ref(null)

const form = ref({
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

async function load() {
  loading.value = true
  try {
    const res = await listAppointmentTypes({ includeInactive: includeInactive.value })
    types.value = res.data.data
  } finally {
    loading.value = false
  }
}

function startEdit(type) {
  editing.value = type.id
  form.value = { ...type }
}

function resetForm() {
  editing.value = null
  form.value = {
    nome: '',
    duration_minutes: 30,
    buffer_minutes: 5,
    valor_particular: 0,
    valor_convenio_default: null,
    min_cancellation_hours: null,
    cor: '#3B82F6',
    descricao: '',
    intent_ia: '',
  }
}

async function save() {
  if (editing.value) {
    await updateAppointmentType(editing.value, form.value)
  } else {
    await createAppointmentType(form.value)
  }
  resetForm()
  await load()
}

async function inactivate(id) {
  if (!confirm('Inativar tipo? (preserva histórico — FR-007)')) return
  await deleteAppointmentType(id)
  await load()
}

onMounted(load)
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Tipos de Atendimento</h1>

    <div class="flex items-center gap-2">
      <label class="text-sm">
        <input v-model="includeInactive" type="checkbox" @change="load" />
        Incluir inativos
      </label>
    </div>

    <table class="w-full border">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-2 text-left">Nome</th>
          <th class="p-2 text-right">Duração</th>
          <th class="p-2 text-right">Valor</th>
          <th class="p-2 text-center">Ativo</th>
          <th class="p-2"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="t in types" :key="t.id" class="border-t">
          <td class="p-2">
            <span :style="{ color: t.cor }">●</span>
            {{ t.nome }}
            <span v-if="t.nome.toLowerCase() === 'retorno'" class="text-xs text-gray-500" title="Tipo: Retorno (categoria de consulta — não confundir com cadência automática Fase 6)"> (categoria)</span>
          </td>
          <td class="p-2 text-right">{{ t.duration_minutes }} min</td>
          <td class="p-2 text-right">R$ {{ t.valor_particular }}</td>
          <td class="p-2 text-center">{{ t.is_active ? '✓' : '—' }}</td>
          <td class="p-2 text-right">
            <button @click="startEdit(t)" class="text-blue-600 mr-2">editar</button>
            <button v-if="t.is_active" @click="inactivate(t.id)" class="text-red-600">inativar</button>
          </td>
        </tr>
      </tbody>
    </table>

    <section class="border rounded p-4 space-y-2">
      <h2 class="font-semibold">{{ editing ? 'Editar tipo' : 'Novo tipo' }}</h2>
      <div class="grid grid-cols-2 gap-2">
        <label class="flex flex-col text-sm">Nome
          <input v-model="form.nome" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm">Duração (min)
          <input v-model.number="form.duration_minutes" type="number" min="5" max="600" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm">Buffer (min)
          <input v-model.number="form.buffer_minutes" type="number" min="0" max="120" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm">Valor particular
          <input v-model.number="form.valor_particular" type="number" step="0.01" min="0" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm">Valor convênio default
          <input v-model.number="form.valor_convenio_default" type="number" step="0.01" min="0" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm">Min cancel hours (override tenant)
          <input v-model.number="form.min_cancellation_hours" type="number" min="0" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm">Cor
          <input v-model="form.cor" type="color" class="border rounded h-9 w-full" />
        </label>
        <label class="flex flex-col text-sm">Intent IA (futura)
          <input v-model="form.intent_ia" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm col-span-2">Descrição
          <textarea v-model="form.descricao" class="border rounded p-1" rows="2"></textarea>
        </label>
      </div>
      <div class="flex gap-2">
        <button @click="save" class="bg-blue-600 text-white px-4 py-2 rounded">{{ editing ? 'Salvar' : 'Criar' }}</button>
        <button v-if="editing" @click="resetForm" class="text-gray-600 px-4 py-2">Cancelar</button>
      </div>
    </section>
  </div>
</template>

<script setup>
/**
 * T059 — Componente reusável para criar/editar tipo de atendimento (US-6.2).
 *
 * Embedded em AppointmentTypesPage.
 */
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue', 'submit'])

const local = ref({ ...props.modelValue })

watch(() => props.modelValue, (v) => { local.value = { ...v } }, { deep: true })

function submit() {
  emit('update:modelValue', local.value)
  emit('submit', { ...local.value })
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-2">
    <input v-model="local.nome" placeholder="Nome (ex.: Consulta)" required class="border rounded p-1 w-full" />
    <div class="grid grid-cols-3 gap-2">
      <input v-model.number="local.duration_minutes" type="number" min="5" max="600" placeholder="Min" class="border rounded p-1" />
      <input v-model.number="local.buffer_minutes" type="number" min="0" max="120" placeholder="Buffer" class="border rounded p-1" />
      <input v-model="local.cor" type="color" class="border rounded h-9" />
    </div>
    <div class="grid grid-cols-2 gap-2">
      <input v-model.number="local.valor_particular" type="number" step="0.01" min="0" placeholder="Valor particular" class="border rounded p-1" />
      <input v-model.number="local.valor_convenio_default" type="number" step="0.01" min="0" placeholder="Valor convênio" class="border rounded p-1" />
    </div>
    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded">Salvar</button>
  </form>
</template>

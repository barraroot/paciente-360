<script setup>
/**
 * T091 — Modal de criação/edição de consulta (US-6.3).
 *
 * Stub funcional mínimo. Próximas iterações: integração com SlotPicker,
 * heartbeat de SlotReservation, validação inline.
 */
import { ref, watch } from 'vue'
import PatientAutocomplete from './PatientAutocomplete.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  initialPayload: { type: Object, default: () => ({}) },
  professionals: { type: Array, default: () => [] },
  appointmentTypes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const form = ref({
  paciente_id: null,
  paciente_label: '',
  professional_id: null,
  appointment_type_id: null,
  starts_at: '',
  channel_origin: 'painel',
  notify_patient: true,
})

watch(() => props.initialPayload, (v) => {
  form.value = { ...form.value, ...v }
}, { immediate: true })

function selectPatient(p) {
  form.value.paciente_id = p.id
  form.value.paciente_label = p.nome
}

function submit() {
  emit('submit', { ...form.value })
}

function close() {
  emit('update:modelValue', false)
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded p-6 w-full max-w-md">
      <h2 class="text-lg font-bold mb-3">Nova consulta</h2>

      <div class="space-y-3">
        <div>
          <label class="text-sm">Paciente</label>
          <p v-if="form.paciente_label" class="text-sm font-medium">{{ form.paciente_label }}</p>
          <PatientAutocomplete @select="selectPatient" />
        </div>

        <label class="flex flex-col text-sm">
          Profissional
          <select v-model="form.professional_id" class="border rounded p-1">
            <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>

        <label class="flex flex-col text-sm">
          Tipo de atendimento
          <select v-model="form.appointment_type_id" class="border rounded p-1">
            <option v-for="t in appointmentTypes" :key="t.id" :value="t.id">{{ t.nome }} ({{ t.duration_minutes }}min)</option>
          </select>
        </label>

        <label class="flex flex-col text-sm">
          Início
          <input v-model="form.starts_at" type="datetime-local" class="border rounded p-1" />
        </label>

        <label class="flex items-center text-sm gap-2">
          <input v-model="form.notify_patient" type="checkbox" />
          Notificar paciente automaticamente
        </label>
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button @click="close" class="px-3 py-1 text-gray-600">Cancelar</button>
        <button @click="submit" :disabled="!form.paciente_id" class="bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-50">Criar</button>
      </div>
    </div>
  </div>
</template>

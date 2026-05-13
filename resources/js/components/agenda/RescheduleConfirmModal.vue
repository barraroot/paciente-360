<script setup>
/**
 * T092 — Modal de confirmação ao arrastar consulta (clarify nº 9).
 *
 * Texto: "Reagendar consulta de {Paciente} de {hora_atual} para {hora_nova}?
 * O paciente será notificado."
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  pacienteName: { type: String, default: '' },
  fromLabel: { type: String, default: '' },
  toLabel: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded p-6 w-full max-w-md">
      <h2 class="text-lg font-bold mb-3">Confirmar reagendamento</h2>
      <p class="text-sm">
        Reagendar consulta de <strong>{{ pacienteName }}</strong>
        de <strong>{{ fromLabel }}</strong> para <strong>{{ toLabel }}</strong>?
        O paciente será notificado.
      </p>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="emit('cancel'); emit('update:modelValue', false)" class="px-3 py-1 text-gray-600">Cancelar</button>
        <button @click="emit('confirm'); emit('update:modelValue', false)" class="bg-blue-600 text-white px-4 py-2 rounded">Confirmar e notificar</button>
      </div>
    </div>
  </div>
</template>

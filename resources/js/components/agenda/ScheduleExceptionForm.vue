<script setup>
/**
 * T048 — Componente reusável para criar bloqueio de agenda (US-6.1).
 *
 * Embedded em ScheduleConfigPage; pode também ser usado em modal isolada
 * a partir do calendário (US-6.3).
 */
import { ref } from 'vue'

const emit = defineEmits(['submit'])

const form = ref({ starts_at: '', ends_at: '', reason: '' })

function submit() {
  emit('submit', { ...form.value })
  form.value = { starts_at: '', ends_at: '', reason: '' }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-2">
    <div class="grid grid-cols-2 gap-2">
      <label class="flex flex-col text-sm">Início
        <input v-model="form.starts_at" type="datetime-local" required class="border rounded p-1" />
      </label>
      <label class="flex flex-col text-sm">Fim
        <input v-model="form.ends_at" type="datetime-local" required class="border rounded p-1" />
      </label>
    </div>
    <label class="flex flex-col text-sm">Motivo
      <input v-model="form.reason" type="text" placeholder="ex.: Férias" class="border rounded p-1" />
    </label>
    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded">Adicionar bloqueio</button>
  </form>
</template>

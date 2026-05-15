<script setup>
/**
 * T114 — Botão de marcação de comparecimento (clarify nº 14).
 *
 * Estados:
 *  - status='scheduled' ou 'confirmed' → 2 botões (Realizada / Não realizada)
 *  - status='realizada' ou 'nao_realizada' → 1 botão Reverter (se dentro 48h)
 *  - auto_flagged_at preenchido → destaque visual "candidato a no-show"
 */
import { computed } from 'vue'
import api from '@/lib/api'

const props = defineProps({
  appointment: { type: Object, required: true },
})
const emit = defineEmits(['updated'])

const isMarked = computed(() =>
  ['realizada', 'nao_realizada', 'concluida_sem_registro'].includes(props.appointment.status)
)

const isAutoFlagged = computed(() => !!props.appointment.auto_flagged_at)

async function mark(status) {
  const motivo = status === 'nao_realizada' ? prompt('Motivo (opcional):') : null
  const { data } = await api.post(`/agenda/consultas/${props.appointment.id}/marcar-comparecimento`, {
    status,
    attendance_motivo: motivo,
  })
  emit('updated', data.data)
}

async function revert() {
  if (!confirm('Reverter marcação de comparecimento?')) return
  const { data } = await api.post(`/agenda/consultas/${props.appointment.id}/reverter-comparecimento`)
  emit('updated', data.data)
}
</script>

<template>
  <div class="flex gap-2 items-center">
    <span v-if="isAutoFlagged" class="text-xs text-amber-600 font-medium" title="Sem confirmação de comparecimento (T+30min)">
      ⚠️ pendente
    </span>

    <template v-if="!isMarked">
      <button @click="mark('realizada')" class="px-2 py-1 bg-green-600 text-white text-xs rounded">Realizada</button>
      <button @click="mark('nao_realizada')" class="px-2 py-1 bg-red-600 text-white text-xs rounded">Não realizada</button>
    </template>

    <template v-else>
      <span class="text-xs text-gray-600">{{ appointment.status }}</span>
      <button v-if="appointment.status !== 'concluida_sem_registro'" @click="revert" class="text-xs text-blue-600 underline">reverter</button>
    </template>
  </div>
</template>

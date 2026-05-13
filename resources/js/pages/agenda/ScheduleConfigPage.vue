<script setup>
/**
 * T047 — Página de configuração de agenda do profissional (US-6.1).
 *
 * Stub funcional mínimo: lista profissionais, permite editar horários
 * recorrentes (7 dias da semana × N blocos) + bloqueios pontuais.
 *
 * Próximas iterações: drag-and-drop reordering de blocks, copy from
 * other professional wizard (clarify nº 5), preview de slots.
 */
import { ref, onMounted } from 'vue'
import { getSchedule, updateSchedule, listExceptions, createException, deleteException } from '@/lib/agendaApi'

const professionalId = ref(null)
const schedule = ref({ schedules: [], accepted_appointment_type_ids: [], timezone: null })
const exceptions = ref([])
const loading = ref(false)

const DAYS = [
  { id: 1, label: 'Segunda' },
  { id: 2, label: 'Terça' },
  { id: 3, label: 'Quarta' },
  { id: 4, label: 'Quinta' },
  { id: 5, label: 'Sexta' },
  { id: 6, label: 'Sábado' },
  { id: 7, label: 'Domingo' },
]

const newException = ref({ starts_at: '', ends_at: '', reason: '' })

async function loadProfessional(id) {
  professionalId.value = id
  loading.value = true
  try {
    const [scheduleRes, exceptionsRes] = await Promise.all([
      getSchedule(id),
      listExceptions(id),
    ])
    schedule.value = scheduleRes.data.data
    exceptions.value = exceptionsRes.data.data
  } finally {
    loading.value = false
  }
}

function addBlockToDay(dayOfWeek) {
  let day = schedule.value.schedules.find((s) => s.day_of_week === dayOfWeek)
  if (!day) {
    day = { day_of_week: dayOfWeek, blocks: [] }
    schedule.value.schedules.push(day)
  }
  day.blocks.push({ start: '08:00', end: '12:00' })
}

function removeBlock(dayOfWeek, idx) {
  const day = schedule.value.schedules.find((s) => s.day_of_week === dayOfWeek)
  if (day) day.blocks.splice(idx, 1)
}

async function save() {
  if (!professionalId.value) return
  loading.value = true
  try {
    const res = await updateSchedule(professionalId.value, {
      timezone: schedule.value.timezone,
      schedules: schedule.value.schedules,
      accepted_appointment_type_ids: schedule.value.accepted_appointment_type_ids,
    })
    schedule.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function addException() {
  if (!professionalId.value) return
  const res = await createException(professionalId.value, newException.value)
  exceptions.value.push(res.data.data)
  newException.value = { starts_at: '', ends_at: '', reason: '' }
}

async function removeException(id) {
  await deleteException(id)
  exceptions.value = exceptions.value.filter((e) => e.id !== id)
}

onMounted(() => {
  // Em produção: pegar professionalId via route params ou query
})
</script>

<template>
  <div class="p-6 space-y-8">
    <h1 class="text-2xl font-bold">Configurar Agenda</h1>

    <div v-if="loading" class="text-gray-500">Carregando...</div>

    <section v-if="professionalId" class="space-y-4">
      <h2 class="text-lg font-semibold">Horários recorrentes</h2>
      <div v-for="day in DAYS" :key="day.id" class="border rounded p-3">
        <div class="flex justify-between items-center mb-2">
          <strong>{{ day.label }}</strong>
          <button @click="addBlockToDay(day.id)" class="text-sm text-blue-600">+ bloco</button>
        </div>
        <div
          v-for="(block, idx) in (schedule.schedules.find((s) => s.day_of_week === day.id)?.blocks || [])"
          :key="idx"
          class="flex gap-2 items-center mb-1"
        >
          <input v-model="block.start" type="time" class="border rounded p-1" />
          <span>-</span>
          <input v-model="block.end" type="time" class="border rounded p-1" />
          <button @click="removeBlock(day.id, idx)" class="text-red-600 text-sm">x</button>
        </div>
      </div>
      <button @click="save" class="bg-blue-600 text-white px-4 py-2 rounded">Salvar</button>
    </section>

    <section v-if="professionalId" class="space-y-4">
      <h2 class="text-lg font-semibold">Bloqueios pontuais</h2>
      <ul class="space-y-1">
        <li v-for="ex in exceptions" :key="ex.id" class="border rounded p-2 flex justify-between">
          <span>{{ ex.starts_at }} → {{ ex.ends_at }} {{ ex.reason ? `— ${ex.reason}` : '' }}</span>
          <button @click="removeException(ex.id)" class="text-red-600 text-sm">remover</button>
        </li>
      </ul>
      <div class="flex gap-2 items-end">
        <label class="flex flex-col text-sm">
          Início
          <input v-model="newException.starts_at" type="datetime-local" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm">
          Fim
          <input v-model="newException.ends_at" type="datetime-local" class="border rounded p-1" />
        </label>
        <label class="flex flex-col text-sm flex-1">
          Motivo
          <input v-model="newException.reason" type="text" class="border rounded p-1" />
        </label>
        <button @click="addException" class="bg-blue-600 text-white px-3 py-1 rounded">Adicionar</button>
      </div>
    </section>
  </div>
</template>

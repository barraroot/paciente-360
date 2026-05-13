<script setup>
/**
 * T094 — Página principal da Agenda com FullCalendar (clarify nº 9).
 *
 * Stub funcional MVP:
 *  - Views diária + semanal (mensal fora do MVP)
 *  - Drag-create: clicar em slot vazio abre modal
 *  - Drag-to-move: confirma via modal antes de salvar
 *  - Toggle multi-prof opcional na semana
 *
 * **Débito técnico**: integração completa com FullCalendar v6 requires
 * import dos plugins e setup específico que mantenho mínimo aqui. Para
 * produção, expandir handlers de eventChange + eventReceive para drag-and-drop
 * completo + multi-resource view.
 */
import { ref, onMounted, computed } from 'vue'
import api from '@/lib/api'
import { useAgendaStore } from '@/stores/agenda'
import { useTimezoneRenderer } from '@/composables/useTimezoneRenderer'
import AppointmentFormModal from '@/components/agenda/AppointmentFormModal.vue'
import RescheduleConfirmModal from '@/components/agenda/RescheduleConfirmModal.vue'

const store = useAgendaStore()

const view = ref('week') // 'day' | 'week'
const multiProf = ref(false)
const professionals = ref([])
const appointmentTypes = ref([])

const showCreateModal = ref(false)
const showRescheduleModal = ref(false)
const initialCreatePayload = ref({})
const rescheduleContext = ref({ pacienteName: '', fromLabel: '', toLabel: '', appointmentId: null, newStartsAt: null })

const renderer = useTimezoneRenderer('America/Sao_Paulo')

const currentRange = computed(() => {
  const now = new Date()
  const start = new Date(now)
  if (view.value === 'week') {
    start.setDate(now.getDate() - now.getDay() + 1)
  }
  start.setHours(0, 0, 0, 0)
  const end = new Date(start)
  end.setDate(start.getDate() + (view.value === 'week' ? 7 : 1))
  return {
    from: start.toISOString(),
    to: end.toISOString(),
  }
})

async function loadDeps() {
  const [profsRes, typesRes] = await Promise.all([
    api.get('/users', { params: { only_professionals: 1 } }).catch(() => ({ data: { data: [] } })),
    api.get('/agenda/appointment-types'),
  ])
  professionals.value = profsRes.data.data || []
  appointmentTypes.value = typesRes.data.data
}

async function loadAppointments() {
  store.range = currentRange.value
  await store.fetch()
}

function openCreate(slotStart) {
  initialCreatePayload.value = { starts_at: slotStart, channel_origin: 'painel' }
  showCreateModal.value = true
}

async function submitCreate(payload) {
  await store.create(payload)
  showCreateModal.value = false
  await loadAppointments()
}

function startReschedule(appointmentId, pacienteName, oldStarts, newStarts) {
  rescheduleContext.value = {
    pacienteName,
    fromLabel: renderer.formatDateTime(oldStarts),
    toLabel: renderer.formatDateTime(newStarts),
    appointmentId,
    newStartsAt: newStarts,
  }
  showRescheduleModal.value = true
}

async function confirmReschedule() {
  await store.reschedule(rescheduleContext.value.appointmentId, {
    new_starts_at: rescheduleContext.value.newStartsAt,
  })
  await loadAppointments()
}

onMounted(async () => {
  await loadDeps()
  await loadAppointments()
})
</script>

<template>
  <div class="p-6 space-y-4">
    <header class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Agenda</h1>
      <div class="flex gap-2 items-center">
        <button @click="view = 'day'; loadAppointments()" :class="{ 'font-bold': view === 'day' }">Diária</button>
        <button @click="view = 'week'; loadAppointments()" :class="{ 'font-bold': view === 'week' }">Semanal</button>
        <label v-if="view === 'week'" class="text-sm flex items-center gap-1">
          <input v-model="multiProf" type="checkbox" /> Multi-profissional
        </label>
      </div>
    </header>

    <!-- TODO: Integrar FullCalendar v6 aqui (eventos = store.appointments) -->
    <!-- Stub: lista simples enquanto integração FullCalendar não está completa -->
    <section v-if="store.loading" class="text-gray-500">Carregando...</section>
    <section v-else>
      <p class="text-sm text-gray-600 mb-2">{{ store.appointments.length }} consulta(s) no período</p>
      <table class="w-full border text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="p-2 text-left">Início</th>
            <th class="p-2 text-left">Status</th>
            <th class="p-2 text-left">Origem</th>
            <th class="p-2 text-right">Valor</th>
            <th class="p-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="apt in store.appointments" :key="apt.id" class="border-t">
            <td class="p-2">{{ renderer.formatDateTime(apt.starts_at) }}</td>
            <td class="p-2">{{ apt.status }}</td>
            <td class="p-2">{{ apt.channel_origin }}</td>
            <td class="p-2 text-right">R$ {{ apt.valor_aplicado }}</td>
            <td class="p-2 text-right">
              <button
                @click="startReschedule(apt.id, '#'+apt.paciente_id, apt.starts_at, prompt('Novo horário ISO 8601:', apt.starts_at))"
                class="text-blue-600 text-xs"
              >
                reagendar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <button
        @click="openCreate(new Date().toISOString())"
        class="mt-3 bg-blue-600 text-white px-4 py-2 rounded"
      >
        + Nova consulta
      </button>
    </section>

    <AppointmentFormModal
      v-model="showCreateModal"
      :initial-payload="initialCreatePayload"
      :professionals="professionals"
      :appointment-types="appointmentTypes"
      @submit="submitCreate"
    />
    <RescheduleConfirmModal
      v-model="showRescheduleModal"
      :paciente-name="rescheduleContext.pacienteName"
      :from-label="rescheduleContext.fromLabel"
      :to-label="rescheduleContext.toLabel"
      @confirm="confirmReschedule"
    />
  </div>
</template>

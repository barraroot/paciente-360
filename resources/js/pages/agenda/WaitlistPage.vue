<script setup>
/**
 * T138 — Página de gestão da lista de espera (US-6.6 — clarify nº 8).
 *
 * Stub funcional MVP: lista entries por (prof × tipo) com filtros, permite
 * inscrever paciente manualmente. Próxima iteração: visualização de fila
 * com posições, action "notificar próximo manualmente" para Atendente.
 */
import { ref, onMounted } from 'vue'
import api from '@/lib/api'

const entries = ref([])
const loading = ref(false)
const filters = ref({ professional_id: null, appointment_type_id: null, status: '' })

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v))
    const { data } = await api.get('/agenda/waitlist', { params })
    entries.value = data.data
  } finally {
    loading.value = false
  }
}

async function cancel(id) {
  if (!confirm('Remover paciente da lista de espera?')) return
  await api.delete(`/agenda/waitlist/${id}`)
  await load()
}

onMounted(load)
</script>

<template>
  <div class="p-6 space-y-4">
    <h1 class="text-2xl font-bold">Lista de Espera</h1>

    <div class="flex gap-2">
      <select v-model="filters.status" @change="load" class="border rounded p-1">
        <option value="">Todos os status</option>
        <option value="waiting">Aguardando</option>
        <option value="notified">Notificado</option>
        <option value="accepted">Aceito</option>
        <option value="expired">Expirado</option>
        <option value="canceled">Cancelado</option>
      </select>
    </div>

    <p v-if="loading" class="text-gray-500">Carregando...</p>
    <p v-else-if="!entries.length" class="text-gray-500">Nenhuma entrada na lista de espera.</p>

    <table v-else class="w-full border">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-2 text-right">Pos.</th>
          <th class="p-2 text-left">Paciente</th>
          <th class="p-2 text-left">Status</th>
          <th class="p-2 text-left">Notificado em</th>
          <th class="p-2 text-left">Expira em</th>
          <th class="p-2"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="e in entries" :key="e.id" class="border-t">
          <td class="p-2 text-right">{{ e.position }}</td>
          <td class="p-2">#{{ e.paciente_id }}</td>
          <td class="p-2">{{ e.status }}</td>
          <td class="p-2">{{ e.notified_at || '—' }}</td>
          <td class="p-2">{{ e.expires_at || '—' }}</td>
          <td class="p-2 text-right">
            <button v-if="['waiting', 'notified'].includes(e.status)" @click="cancel(e.id)" class="text-red-600 text-xs">remover</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

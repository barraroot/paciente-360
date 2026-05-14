<script setup>
/**
 * T169 — Página de configuração Google Calendar sync (US-6.7).
 *
 * Stub MVP. Outlook exibido como "em breve" desabilitado (clarify nº 11).
 */
import { ref, onMounted } from 'vue'
import api from '@/lib/api'

const account = ref(null)
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/agenda/calendar-sync')
    account.value = data.data
  } finally {
    loading.value = false
  }
}

async function connect() {
  const profId = prompt('Professional ID a conectar:')
  if (!profId) return
  const { data } = await api.post('/agenda/calendar-sync/google/connect', {
    professional_id: Number(profId),
    provider: 'google',
  })
  // Em produção: redirect para data.authorize_url
  window.location.href = data.authorize_url
}

async function disconnect() {
  if (!account.value || !confirm('Desconectar Google Calendar?')) return
  await api.post(`/agenda/calendar-sync/${account.value.id}/disconnect`)
  await load()
}

onMounted(load)
</script>

<template>
  <div class="p-6 space-y-4 max-w-2xl">
    <h1 class="text-2xl font-bold">Sincronização de Calendário</h1>

    <section class="border rounded p-4 space-y-2">
      <h2 class="font-semibold">Google Calendar</h2>

      <p v-if="loading" class="text-gray-500">Carregando...</p>

      <template v-else-if="account && account.status === 'connected'">
        <p class="text-green-700 font-medium">✓ Conectado como {{ account.provider_email }}</p>
        <p class="text-sm text-gray-600">
          Sub-calendário: <code>{{ account.google_calendar_id }}</code>
        </p>
        <p v-if="account.last_synced_at" class="text-xs text-gray-500">
          Última sincronização: {{ account.last_synced_at }}
        </p>
        <button @click="disconnect" class="text-red-600 text-sm underline">desconectar</button>
      </template>

      <template v-else-if="account && account.status === 'disconnected'">
        <p class="text-amber-700">⚠️ Sincronização interrompida</p>
        <button @click="connect" class="bg-blue-600 text-white px-4 py-2 rounded">Reconectar Google</button>
      </template>

      <template v-else>
        <p class="text-gray-600 text-sm">Nenhum calendário conectado.</p>
        <button @click="connect" class="bg-blue-600 text-white px-4 py-2 rounded">Conectar Google Calendar</button>
      </template>
    </section>

    <section class="border rounded p-4 space-y-2 opacity-60">
      <h2 class="font-semibold">Microsoft Outlook</h2>
      <p class="text-sm text-gray-500">Em breve — Fase 6 do roadmap (clarify nº 11)</p>
      <button disabled class="bg-gray-300 text-gray-500 px-4 py-2 rounded cursor-not-allowed">Conectar (indisponível)</button>
    </section>
  </div>
</template>

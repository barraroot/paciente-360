<script setup>
/**
 * T170 — Banner persistente para sync desconectada (R5).
 *
 * Montar em App.vue para profissionais com `calendar_sync.configure`.
 */
import { ref, onMounted } from 'vue'
import api from '@/lib/api'

const account = ref(null)
const dismissed = ref(false)

async function load() {
  try {
    const { data } = await api.get('/agenda/calendar-sync')
    account.value = data.data
  } catch (e) { /* ignore */ }
}

function dismiss() {
  dismissed.value = true
  // Persiste por 24h
  localStorage.setItem('agenda.calendar_sync.dismissed_until', Date.now() + 24 * 3600 * 1000)
}

function reconnect() {
  window.location.href = '/agenda/sincronizacao'
}

onMounted(() => {
  const until = Number(localStorage.getItem('agenda.calendar_sync.dismissed_until') || 0)
  if (Date.now() < until) {
    dismissed.value = true
    return
  }
  load()
})
</script>

<template>
  <div
    v-if="account && account.status === 'disconnected' && !dismissed"
    class="bg-amber-100 border-b border-amber-300 px-4 py-2 flex justify-between items-center"
  >
    <span class="text-sm">
      ⚠️ Sincronização do Google Calendar interrompida.
      Suas consultas no CRM não estão sendo espelhadas.
    </span>
    <div class="flex gap-2">
      <button @click="reconnect" class="bg-blue-600 text-white text-sm px-3 py-1 rounded">Reconectar</button>
      <button @click="dismiss" class="text-gray-600 text-sm">Dispensar</button>
    </div>
  </div>
</template>

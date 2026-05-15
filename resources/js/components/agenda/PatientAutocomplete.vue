<script setup>
/**
 * T090 — Busca paciente trgm (Fase 2 reusada) com cadastro rápido.
 */
import { ref, watch } from 'vue'
import api from '@/lib/api'

const emit = defineEmits(['select'])

const query = ref('')
const results = ref([])
const loading = ref(false)
let timer = null

watch(query, (v) => {
  clearTimeout(timer)
  if (!v || v.length < 2) {
    results.value = []
    return
  }
  timer = setTimeout(async () => {
    loading.value = true
    try {
      const { data } = await api.get('/pacientes', { params: { search: v, per_page: 10 } })
      results.value = data.data || []
    } finally {
      loading.value = false
    }
  }, 250)
})

function pick(p) {
  emit('select', p)
  query.value = ''
  results.value = []
}
</script>

<template>
  <div class="relative">
    <input
      v-model="query"
      type="text"
      placeholder="Buscar paciente por nome/CPF..."
      class="border rounded p-2 w-full"
    />
    <div v-if="loading" class="text-xs text-gray-500 absolute right-2 top-2">...</div>
    <ul v-if="results.length" class="absolute bg-white border rounded mt-1 w-full max-h-60 overflow-auto z-10">
      <li
        v-for="p in results"
        :key="p.id"
        @click="pick(p)"
        class="px-2 py-1 hover:bg-gray-100 cursor-pointer"
      >
        {{ p.nome }} <span class="text-xs text-gray-500">{{ p.cpf || p.telefone_primario }}</span>
      </li>
    </ul>
  </div>
</template>

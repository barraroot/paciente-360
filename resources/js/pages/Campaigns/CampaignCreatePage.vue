<script setup>
/**
 * **T169 (Fase 8 — Lote C US-9.1)** — Formulário de criação (AC-9.1.1).
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCampaignsStore } from '@/stores/campaignsStore'

const router = useRouter()
const store = useCampaignsStore()

const form = ref({
  name: '',
  channel: 'whatsapp',
  template_id: null,
  scheduled_for: '',
  audience_filters: {
    inactivity_months: 6,
    tags: [],
    age_range: { min: null, max: null },
    gender: '',
  },
})

const tagsInput = ref('')

async function submit() {
  const payload = { ...form.value }
  // Parse tags string → array.
  if (tagsInput.value.trim()) {
    payload.audience_filters.tags = tagsInput.value.split(',').map((t) => t.trim()).filter(Boolean)
  }
  if (payload.scheduled_for === '') {
    delete payload.scheduled_for
  }
  // Limpa age_range vazio.
  if (!payload.audience_filters.age_range.min && !payload.audience_filters.age_range.max) {
    delete payload.audience_filters.age_range
  }
  if (!payload.audience_filters.gender) {
    delete payload.audience_filters.gender
  }

  try {
    const created = await store.create(payload)
    router.push(`/campaigns/${created.id}`)
  } catch (_) { /* erro já tratado */ }
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6 p-6">
    <header>
      <h1 class="text-2xl font-semibold">Nova campanha</h1>
      <p class="mt-1 text-sm text-gray-600">
        Configure segmentação + template. Pré-visualize antes de disparar.
      </p>
    </header>

    <form @submit.prevent="submit" class="space-y-4 rounded border bg-white p-6">
      <div>
        <label class="block text-sm font-medium text-gray-700">Nome da campanha</label>
        <input v-model="form.name" type="text" required maxlength="200"
               class="mt-1 w-full rounded border-gray-300 text-sm" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Canal</label>
        <select v-model="form.channel" required class="mt-1 w-full rounded border-gray-300 text-sm">
          <option value="whatsapp">WhatsApp</option>
          <option value="instagram">Instagram</option>
        </select>
        <p class="mt-1 text-xs text-gray-500">Q3 — canal único por campanha.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Template HSM (ID)</label>
        <input v-model.number="form.template_id" type="number"
               class="mt-1 w-full rounded border-gray-300 text-sm"
               placeholder="ID do template aprovado pela Meta" />
        <p class="mt-1 text-xs text-gray-500">Templates devem ter unsubscribe (AC-9.3.3).</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Agendar para</label>
        <input v-model="form.scheduled_for" type="datetime-local"
               class="mt-1 w-full rounded border-gray-300 text-sm" />
        <p class="mt-1 text-xs text-gray-500">Vazio = dispatch manual via página de detalhes.</p>
      </div>

      <fieldset class="border border-gray-200 rounded p-4">
        <legend class="text-sm font-medium text-gray-700 px-2">Segmentação</legend>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-700">Inatividade (meses sem consulta realizada)</label>
            <input v-model.number="form.audience_filters.inactivity_months" type="number" min="1" max="60"
                   class="mt-1 w-full rounded border-gray-300 text-sm" />
            <p class="mt-1 text-xs text-gray-500">Q1 — última `ConsultaRealizada` (Fase 5).</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700">Tags (separadas por vírgula)</label>
            <input v-model="tagsInput" type="text"
                   class="mt-1 w-full rounded border-gray-300 text-sm"
                   placeholder="ex.: vacinação, preventivo" />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs font-medium text-gray-700">Idade mín.</label>
              <input v-model.number="form.audience_filters.age_range.min" type="number" min="0" max="120"
                     class="mt-1 w-full rounded border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700">Idade máx.</label>
              <input v-model.number="form.audience_filters.age_range.max" type="number" min="0" max="120"
                     class="mt-1 w-full rounded border-gray-300 text-sm" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700">Gênero</label>
            <select v-model="form.audience_filters.gender" class="mt-1 w-full rounded border-gray-300 text-sm">
              <option value="">Todos</option>
              <option value="M">Masculino</option>
              <option value="F">Feminino</option>
              <option value="O">Outro</option>
            </select>
          </div>
        </div>
      </fieldset>

      <div v-if="store.error" role="alert" class="rounded border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">
        {{ store.error }}
      </div>

      <div class="flex justify-end gap-2">
        <RouterLink to="/campaigns" class="rounded border px-4 py-2 text-sm hover:bg-gray-50">Cancelar</RouterLink>
        <button type="submit" :disabled="store.saving"
                class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
          {{ store.saving ? 'Criando…' : 'Criar campanha' }}
        </button>
      </div>
    </form>
  </div>
</template>

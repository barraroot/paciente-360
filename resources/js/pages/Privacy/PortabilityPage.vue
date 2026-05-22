<script setup>
/**
 * **T060 (Fase 8 — Lote A US-13.2)** — Painel Admin Clínica de Portabilidade (Q28).
 *
 * Acceptance:
 *   - AC-13.2.8: gerar arquivo JSON estruturado, schema v1.0.
 *   - URL assinada 7 dias, audit do download.
 */
import { onMounted, ref } from 'vue'
import { usePrivacyStore } from '@/stores/privacyStore'

const store = usePrivacyStore()

const showGenerateModal = ref(false)
const target = ref(null)

onMounted(() => {
  store.fetchPortabilityRequests()
})

function openGenerate(req) {
  target.value = req
  showGenerateModal.value = true
}

async function confirmGenerate() {
  if (!target.value) return
  try {
    await store.executePortability(target.value.id)
    showGenerateModal.value = false
    target.value = null
  } catch (_) { /* erro tratado */ }
}

function fmtBytes(b) {
  if (!b) return '—'
  if (b < 1024) return `${b} B`
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`
  return `${(b / 1024 / 1024).toFixed(2)} MB`
}

function fmtDate(iso) {
  return iso ? new Date(iso).toLocaleString('pt-BR') : '—'
}
</script>

<template>
  <div class="space-y-6 p-6">
    <header>
      <h1 class="text-2xl font-semibold">Portabilidade de Dados</h1>
      <p class="mt-1 text-sm text-gray-600">
        LGPD Art. 18º V — gerar arquivo JSON estruturado para entrega ao titular.
      </p>
    </header>

    <section v-if="store.error" role="alert" class="rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
      {{ store.error }}
    </section>

    <section class="overflow-hidden rounded border bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
          <tr>
            <th class="px-4 py-2">Paciente</th>
            <th class="px-4 py-2">Solicitada em</th>
            <th class="px-4 py-2">Status</th>
            <th class="px-4 py-2">Tamanho</th>
            <th class="px-4 py-2">URL expira em</th>
            <th class="px-4 py-2">Baixado em</th>
            <th class="px-4 py-2 text-right">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-if="store.loading">
            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">Carregando…</td>
          </tr>
          <tr v-else-if="store.portabilityRequests.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">
              Nenhuma solicitação de portabilidade.
            </td>
          </tr>
          <tr v-else v-for="r in store.portabilityRequests" :key="r.id" class="hover:bg-gray-50">
            <td class="px-4 py-2 font-mono text-xs">#{{ r.patient_id }}</td>
            <td class="px-4 py-2 text-xs">{{ fmtDate(r.requested_at) }}</td>
            <td class="px-4 py-2">
              <span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ r.status_label }}</span>
            </td>
            <td class="px-4 py-2 text-xs">{{ fmtBytes(r.file_size_bytes) }}</td>
            <td class="px-4 py-2 text-xs">{{ fmtDate(r.url_expires_at) }}</td>
            <td class="px-4 py-2 text-xs">{{ fmtDate(r.downloaded_at) }}</td>
            <td class="px-4 py-2 text-right">
              <button
                v-if="r.status === 'open'"
                type="button"
                class="text-xs text-emerald-700 underline"
                @click="openGenerate(r)"
              >
                Gerar arquivo
              </button>
              <span v-else-if="r.status === 'ready'" class="text-xs text-emerald-700">
                Pronto
              </span>
              <span v-else class="text-xs text-gray-500">{{ r.status_label }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <Teleport to="body">
      <div
        v-if="showGenerateModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="gen-title"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-4"
        @keydown.esc.prevent="showGenerateModal = false"
        @click.self="showGenerateModal = false"
      >
        <div class="w-full max-w-lg rounded bg-white p-6 shadow-xl">
          <h2 id="gen-title" class="text-lg font-semibold">Gerar arquivo de portabilidade</h2>
          <p class="mt-2 text-sm text-gray-700">
            Será gerado um arquivo <strong>JSON estruturado (schema v1.0)</strong> com:
          </p>
          <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-gray-700">
            <li>Dados cadastrais (nome, CPF, telefone, e-mail, endereço, convênio)</li>
            <li>Timeline pública (consultas realizadas, agendamentos)</li>
            <li>Receituários (com itens de controladas mascarados)</li>
            <li>Histórico de consentimentos</li>
          </ul>
          <p class="mt-3 rounded border border-blue-200 bg-blue-50 p-2 text-xs text-blue-900">
            URL assinada válida por 7 dias. Após download, paciente pode solicitar novo link sem reiniciar o deadline LGPD.
          </p>

          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded border px-3 py-1.5 text-sm hover:bg-gray-50" @click="showGenerateModal = false">
              Cancelar
            </button>
            <button
              type="button"
              class="rounded bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
              :disabled="store.saving"
              @click="confirmGenerate"
            >
              {{ store.saving ? 'Gerando…' : 'Gerar arquivo' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
/**
 * **T103 (Fase 8 — Lote B US-12.1)** — Banner persistente de impersonate (AC-12.1.5).
 *
 * Exibido em TODAS as telas do tenant quando Super Admin está com sessão
 * ativa. Lê headers `X-Impersonate-Active` e `X-Impersonate-Session-Id`
 * que o backend (middleware ImpersonateContextResolver) inclui em cada response.
 *
 * Action "Sair do impersonate" chama endpoint dedicado que fecha a sessão e
 * redireciona o Super Admin de volta ao Filament panel.
 *
 * **Como integrar globalmente**: importar em `App.vue` ou no layout principal:
 *   <ImpersonateBanner />
 */
import { ref, onMounted } from 'vue'
import api from '@/lib/api'

const isActive = ref(false)
const sessionId = ref(null)
const ending = ref(false)
const endError = ref('')

/**
 * Lê o status de impersonate via header da última response.
 * Como axios interceptor já tem acesso aos headers, podemos usar
 * /me ou checagem dedicada. Aqui usa checagem on-mount + após cada response.
 */
async function checkStatus() {
  try {
    const response = await api.get('/me/impersonate-status')
    isActive.value = response.data.active === true
    sessionId.value = response.data.session_id ?? null
  } catch (_) {
    // 404 esperado em ambientes sem o endpoint ainda implementado — banner fica oculto.
    isActive.value = false
  }
}

async function endSession() {
  if (!sessionId.value || ending.value) return
  ending.value = true
  endError.value = ''
  try {
    await api.post(`/super-admin/impersonate/${sessionId.value}/end`)
    isActive.value = false
    sessionId.value = null
    // Redireciona Super Admin de volta ao Filament panel.
    window.location.href = '/admin'
  } catch (e) {
    endError.value = 'Falha ao encerrar sessão de impersonate. Tente novamente.'
  } finally {
    ending.value = false
  }
}

onMounted(() => {
  checkStatus()

  // Re-checa a cada 60s (em caso de sessão expirar TTL 2h).
  setInterval(checkStatus, 60000)
})
</script>

<template>
  <div
    v-if="isActive"
    role="alert"
    aria-live="polite"
    class="sticky top-0 z-[100] w-full bg-amber-400 text-amber-950 shadow-md"
  >
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2 text-sm">
      <div class="flex items-center gap-2">
        <span aria-hidden="true">⚠️</span>
        <strong>MODO IMPERSONATE</strong>
        <span>— você está visualizando como suporte. Todas as telas visitadas são auditadas.</span>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="endError" class="text-xs font-medium text-rose-800">{{ endError }}</span>
        <button
          type="button"
          :disabled="ending"
          class="rounded border border-amber-900 bg-amber-300 px-3 py-1 text-xs font-medium hover:bg-amber-200 disabled:opacity-50"
          @click="endSession"
        >
          {{ ending ? 'Encerrando…' : 'Sair do impersonate' }}
        </button>
      </div>
    </div>
  </div>
</template>

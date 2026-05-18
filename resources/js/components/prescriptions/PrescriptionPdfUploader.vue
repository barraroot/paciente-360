<script setup>
/**
 * T080 (Fase 7) — Upload/download de PDF de receituário (US-8.1 / Q7).
 *
 * Features:
 *  - Drag-and-drop + click-to-select (input file oculto).
 *  - Validação client-side: MIME=application/pdf, tamanho ≤ 10MB.
 *  - Progress bar via axios onUploadProgress.
 *  - Toast local (padrão Fase 6) com aria-live.
 *  - "Substituir PDF" se já houver pdf_version > 0.
 *  - "Baixar versão atual" chama requestDownloadUrl e abre nova aba.
 *
 * A11y: role=status em progress, aria-live=polite no toast, label visível.
 */
import { ref, computed } from 'vue'
import { usePrescriptionsStore } from '@/stores/prescriptionsStore'

const props = defineProps({
  prescription: {
    type: Object,
    required: true,
  },
})

const store = usePrescriptionsStore()

// ─── Estado ───────────────────────────────────────────────────────────────────

const dragging = ref(false)
const progress = ref(0)
const validationError = ref(null)
const fileInputRef = ref(null)

const hasPdf = computed(() => (props.prescription?.pdf_version ?? 0) > 0)

// ─── Toast local (padrão Fase 6) ─────────────────────────────────────────────

const toast = ref(null)
let toastTimer = null

function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => {
    toast.value = null
  }, 5000)
}

// ─── Validação ────────────────────────────────────────────────────────────────

const MAX_SIZE = 10 * 1024 * 1024 // 10MB

function validateFile(file) {
  if (!file) return 'Nenhum arquivo selecionado.'
  if (file.type !== 'application/pdf') return 'O arquivo deve ser um PDF.'
  if (file.size > MAX_SIZE) return 'O arquivo não pode ultrapassar 10 MB.'
  return null
}

// ─── Upload ───────────────────────────────────────────────────────────────────

async function startUpload(file) {
  validationError.value = validateFile(file)
  if (validationError.value) return

  progress.value = 1
  try {
    await store.uploadPdf(props.prescription.id, file, (pct) => {
      progress.value = pct
    })
    showToast('PDF anexado com sucesso.')
  } catch (e) {
    showToast(e?.response?.data?.message ?? 'Falha no upload. Tente novamente.', 'error')
  } finally {
    progress.value = 0
    if (fileInputRef.value) fileInputRef.value.value = ''
  }
}

// ─── Handlers de interação ────────────────────────────────────────────────────

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (file) startUpload(file)
}

function onDrop(e) {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) startUpload(file)
}

function onDragOver(e) {
  e.preventDefault()
  dragging.value = true
}

function onDragLeave() {
  dragging.value = false
}

function openFilePicker() {
  fileInputRef.value?.click()
}

// ─── Download ─────────────────────────────────────────────────────────────────

async function downloadCurrent() {
  try {
    const { url } = await store.requestDownloadUrl(props.prescription.id)
    if (url) window.open(url, '_blank', 'noopener,noreferrer')
    else showToast('URL de download não disponível.', 'error')
  } catch {
    showToast('Não foi possível obter o link de download.', 'error')
  }
}
</script>

<template>
  <div class="space-y-3">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-medium text-foreground">PDF da Receita</h3>
      <div v-if="hasPdf" class="flex items-center gap-2">
        <span class="text-xs text-foreground-muted">
          Versão atual: v{{ prescription.pdf_version }}
        </span>
        <button
          type="button"
          class="text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
          @click="downloadCurrent"
        >
          Baixar versão atual
        </button>
      </div>
    </div>

    <!-- Zona de drop -->
    <div
      class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 transition"
      :class="[
        dragging
          ? 'border-primary-400 bg-primary-50'
          : 'border-border bg-surface-elevated hover:border-primary-300',
        store.uploading ? 'pointer-events-none opacity-60' : 'cursor-pointer',
      ]"
      role="region"
      aria-label="Área de upload de PDF"
      @click="openFilePicker"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
    >
      <!-- Ícone -->
      <svg
        class="mb-2 h-8 w-8 text-foreground-muted"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        aria-hidden="true"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
        />
      </svg>

      <p class="text-sm text-foreground">
        <span v-if="hasPdf">Substituir PDF</span>
        <span v-else>Anexar PDF</span>
      </p>
      <p class="mt-1 text-xs text-foreground-muted">
        Arraste um arquivo PDF ou clique para selecionar (máx. 10 MB)
      </p>

      <!-- Input oculto -->
      <input
        ref="fileInputRef"
        type="file"
        accept="application/pdf"
        class="sr-only"
        aria-label="Selecionar arquivo PDF"
        tabindex="-1"
        @change="onFileChange"
      />
    </div>

    <!-- Erro de validação client-side -->
    <p
      v-if="validationError"
      role="alert"
      class="text-xs text-danger-600"
    >
      {{ validationError }}
    </p>

    <!-- Progress bar -->
    <div
      v-if="store.uploading && progress > 0"
      role="status"
      aria-label="Progresso do upload"
      aria-live="polite"
      class="space-y-1"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs text-foreground-muted">Enviando...</span>
        <span class="text-xs font-medium text-foreground">{{ progress }}%</span>
      </div>
      <div class="h-1.5 w-full overflow-hidden rounded-full bg-border">
        <div
          class="h-full rounded-full bg-primary-500 transition-all duration-200"
          :style="{ width: `${progress}%` }"
        />
      </div>
    </div>

    <!-- Toast local (padrão Fase 6) -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toast"
        role="alert"
        aria-live="polite"
        class="flex items-center gap-2 rounded-lg px-4 py-3 text-sm shadow"
        :class="[
          toast.type === 'error'
            ? 'bg-danger-50 text-danger-700 ring-1 ring-danger-200'
            : 'bg-success-50 text-success-700 ring-1 ring-success-200',
        ]"
      >
        <svg v-if="toast.type === 'error'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
        <svg v-else class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ toast.message }}
      </div>
    </Transition>
  </div>
</template>

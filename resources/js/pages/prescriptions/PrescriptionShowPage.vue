<script setup>
/**
 * T084 (Fase 7) — Página de detalhe de receituário (US-8.1).
 *
 * Features:
 *  - Cabeçalho: paciente (link), profissional, tipo (badge), status (pill), criticidade, datas.
 *  - ControlledMaskingBanner se masked.
 *  - Lista de items read-only.
 *  - Campo notes editável inline (botão "Editar observações" → textarea + Save/Cancel).
 *  - Botões: "Cancelar receita" (modal), "Renovar" (placeholder), "Baixar PDF".
 *  - PrescriptionPdfUploader abaixo.
 *  - Timeline simplificada: criada em / cancelada em.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { DateTime } from 'luxon'
import { usePrescriptionsStore } from '@/stores/prescriptionsStore'
import { useAuthStore } from '@/stores/auth'
import PrescriptionTypeBadge from '@/components/prescriptions/PrescriptionTypeBadge.vue'
import PrescriptionStatusPill from '@/components/prescriptions/PrescriptionStatusPill.vue'
import ControlledMaskingBanner from '@/components/prescriptions/ControlledMaskingBanner.vue'
import PrescriptionCancelModal from '@/components/prescriptions/PrescriptionCancelModal.vue'
import PrescriptionPdfUploader from '@/components/prescriptions/PrescriptionPdfUploader.vue'
import PrescriptionAlertConfigToggle from '@/components/prescriptions/PrescriptionAlertConfigToggle.vue'

const route = useRoute()
const router = useRouter()
const store = usePrescriptionsStore()
const auth = useAuthStore()

const id = computed(() => route.params.id)

// ─── Estado ───────────────────────────────────────────────────────────────────

const showCancelModal = ref(false)
const editingNotes = ref(false)
const notesEdit = ref('')
const savingNotes = ref(false)
const notesError = ref(null)

// ─── Toast ────────────────────────────────────────────────────────────────────

const toast = ref(null)
let toastTimer = null

function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => { toast.value = null }, 5000)
}

// ─── Carregamento ─────────────────────────────────────────────────────────────

// ─── Reverb — refresh em tempo real (T116) ────────────────────────────────────

function subscribeRealtimeRefresh() {
  const echo = window.Echo
  const tenantId = auth.currentTenantId
  if (!echo || !tenantId) return

  try {
    echo.private(`prescriptions.${tenantId}`)
      .listen('.PrescriptionsReportRefresh', async (event) => {
        if (event.prescription_id && Number(event.prescription_id) === Number(id.value)) {
          try {
            await store.fetchOne(id.value)
          } catch {
            // Silencioso — erro já em store.error
          }
        }
      })
  } catch {
    // Echo não disponível — silencioso
  }
}

function unsubscribeRealtimeRefresh() {
  const echo = window.Echo
  const tenantId = auth.currentTenantId
  if (!echo || !tenantId) return
  try {
    echo.leave(`prescriptions.${tenantId}`)
  } catch {
    // Silencioso
  }
}

onMounted(async () => {
  try {
    await store.fetchOne(id.value)
  } catch {
    // erro já em store.error
  }
  subscribeRealtimeRefresh()
})

onUnmounted(() => {
  unsubscribeRealtimeRefresh()
  if (toastTimer) clearTimeout(toastTimer)
})

const prescription = computed(() => store.current)

// ─── Criticidade ──────────────────────────────────────────────────────────────

const criticality = computed(() => {
  if (!prescription.value || prescription.value.status !== 'active') return null
  const diff = Math.ceil(
    (new Date(prescription.value.expires_at) - new Date()) / 86400000,
  )
  if (diff > 30) return { level: 'green', label: 'Válida', classes: 'text-success-600 bg-success-50 ring-success-200' }
  if (diff > 7) return { level: 'yellow', label: 'Vence em breve', classes: 'text-warning-700 bg-warning-50 ring-warning-200' }
  return { level: 'red', label: 'Crítica ou vencida', classes: 'text-danger-700 bg-danger-50 ring-danger-200' }
})

// ─── Formatação ───────────────────────────────────────────────────────────────

function fmtDate(iso) {
  if (!iso) return '—'
  return DateTime.fromISO(iso).toFormat('dd/MM/yyyy')
}

function fmtDateTime(iso) {
  if (!iso) return '—'
  return DateTime.fromISO(iso).toFormat("dd/MM/yyyy 'às' HH:mm", { locale: 'pt-BR' })
}

// ─── Edição de notes ──────────────────────────────────────────────────────────

function startEditNotes() {
  notesEdit.value = prescription.value?.notes ?? ''
  notesError.value = null
  editingNotes.value = true
}

function cancelEditNotes() {
  editingNotes.value = false
  notesError.value = null
}

async function saveNotes() {
  savingNotes.value = true
  notesError.value = null
  try {
    await store.updateNotes(id.value, notesEdit.value || null)
    editingNotes.value = false
    showToast('Observações atualizadas.')
  } catch (e) {
    notesError.value = e?.response?.data?.message ?? 'Erro ao salvar observações.'
  } finally {
    savingNotes.value = false
  }
}

// ─── Cancelamento ─────────────────────────────────────────────────────────────

function onCancelled() {
  showToast('Receita cancelada.')
}

// ─── Download PDF ─────────────────────────────────────────────────────────────

async function downloadPdf() {
  try {
    const { url } = await store.requestDownloadUrl(id.value)
    if (url) window.open(url, '_blank', 'noopener,noreferrer')
    else showToast('URL de download não disponível.', 'error')
  } catch {
    showToast('Não foi possível obter o link de download.', 'error')
  }
}

// ─── Timeline simplificada ────────────────────────────────────────────────────

const timelineEvents = computed(() => {
  if (!prescription.value) return []
  const events = []

  if (prescription.value.created_at) {
    events.push({
      key: 'created',
      label: 'Criada',
      date: prescription.value.created_at,
      icon: 'plus',
      classes: 'text-success-600',
    })
  }

  if (prescription.value.cancelled_at) {
    const catLabel = {
      erro_emissao: 'Erro de emissão',
      desistencia_paciente: 'Desistência do paciente',
      substituicao: 'Substituição',
      outro: 'Outro',
    }[prescription.value.cancellation_reason_category] ?? ''

    events.push({
      key: 'cancelled',
      label: `Cancelada${catLabel ? ` — ${catLabel}` : ''}`,
      date: prescription.value.cancelled_at,
      icon: 'x',
      classes: 'text-danger-600',
    })
  }

  return events
})
</script>

<template>
  <div class="min-h-screen bg-background">
    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">

      <!-- Voltar -->
      <div class="mb-6 flex items-center gap-3">
        <router-link
          :to="{ name: 'prescriptions.index' }"
          class="rounded-md p-1 text-foreground-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
          aria-label="Voltar para lista"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
          </svg>
        </router-link>
        <h1 class="text-xl font-semibold text-foreground">Detalhe da Receita</h1>
      </div>

      <!-- Loading -->
      <div v-if="store.loading" aria-busy="true" aria-label="Carregando" class="space-y-4">
        <div class="h-32 rounded-xl bg-surface-elevated animate-pulse" />
        <div class="h-40 rounded-xl bg-surface-elevated animate-pulse" />
      </div>

      <!-- Erro -->
      <div v-else-if="store.error && !prescription" class="rounded-xl border border-border bg-surface p-8 text-center">
        <p class="text-sm text-danger-600 mb-3">{{ store.error }}</p>
        <button
          type="button"
          class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
          @click="store.fetchOne(id)"
        >
          Tentar novamente
        </button>
      </div>

      <template v-else-if="prescription">
        <!-- Banner mascarado -->
        <div class="mb-4">
          <ControlledMaskingBanner :masked="prescription.masked === true" />
        </div>

        <!-- Cabeçalho da receita -->
        <div class="rounded-xl border border-border bg-surface p-5 space-y-4 mb-4">
          <!-- Linha 1: tipo + status + criticidade -->
          <div class="flex flex-wrap items-center gap-2">
            <PrescriptionTypeBadge :type="prescription.type" />
            <PrescriptionStatusPill :status="prescription.status" />
            <span
              v-if="criticality"
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1"
              :class="criticality.classes"
            >
              <svg class="h-2.5 w-2.5" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true">
                <circle cx="4" cy="4" r="4" />
              </svg>
              {{ criticality.label }}
            </span>
          </div>

          <!-- Linha 2: paciente + profissional -->
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <p class="text-xs font-medium text-foreground-muted mb-0.5">Paciente</p>
              <p v-if="prescription.masked" class="text-sm text-foreground-muted tracking-widest">•••</p>
              <router-link
                v-else-if="prescription.patient_id"
                :to="{ name: 'pacientes.show', params: { id: prescription.patient_id } }"
                class="text-sm font-medium text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              >
                {{ prescription.patient_name ?? `Paciente #${prescription.patient_id}` }}
              </router-link>
            </div>
            <div>
              <p class="text-xs font-medium text-foreground-muted mb-0.5">Profissional</p>
              <p class="text-sm text-foreground">{{ prescription.professional_name ?? '—' }}</p>
            </div>
          </div>

          <!-- Linha 3: datas -->
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <p class="text-xs font-medium text-foreground-muted mb-0.5">Emitida em</p>
              <p class="text-sm text-foreground">{{ fmtDate(prescription.issued_at) }}</p>
            </div>
            <div>
              <p class="text-xs font-medium text-foreground-muted mb-0.5">Validade</p>
              <p class="text-sm text-foreground">{{ fmtDate(prescription.expires_at) }}</p>
            </div>
            <div v-if="prescription.cancelled_at">
              <p class="text-xs font-medium text-foreground-muted mb-0.5">Cancelada em</p>
              <p class="text-sm text-danger-600">{{ fmtDate(prescription.cancelled_at) }}</p>
            </div>
          </div>

          <!-- Botões de ação -->
          <div class="flex flex-wrap items-center gap-2 border-t border-border pt-4">
            <!-- Cancelar -->
            <button
              v-if="prescription.status === 'active' && !prescription.masked"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-danger-200 px-3 py-1.5 text-sm font-medium text-danger-700 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
              @click="showCancelModal = true"
            >
              Cancelar receita
            </button>

            <!-- Renovar — placeholder US-8.3 -->
            <!-- TODO US-8.3: Renovação via IA — disponível em Lote D -->
            <span
              class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground-muted"
              title="Disponível em breve"
              aria-disabled="true"
            >
              Renovar
              <span class="rounded bg-surface-elevated px-1 text-xs">Em breve</span>
            </span>

            <!-- Baixar PDF -->
            <button
              v-if="(prescription.pdf_version ?? 0) > 0"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-foreground hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
              @click="downloadPdf"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              Baixar PDF
            </button>
          </div>
        </div>

        <!-- Medicamentos -->
        <div
          v-if="!prescription.masked && prescription.items?.length"
          class="rounded-xl border border-border bg-surface p-5 mb-4 space-y-3"
        >
          <h2 class="text-sm font-semibold text-foreground">
            Medicamentos ({{ prescription.items.length }})
          </h2>
          <div
            v-for="(item, idx) in prescription.items"
            :key="item.id ?? idx"
            class="rounded-lg bg-surface-elevated p-4 space-y-2"
          >
            <p class="text-xs font-semibold text-foreground-muted uppercase tracking-wide">
              Medicamento {{ idx + 1 }}
            </p>
            <p class="text-sm font-medium text-foreground">{{ item.medication_name }}</p>
            <div class="grid grid-cols-2 gap-2 text-xs text-foreground-muted sm:grid-cols-3">
              <span v-if="item.concentration">
                <span class="font-medium">Concentração:</span> {{ item.concentration }}
              </span>
              <span v-if="item.pharmaceutical_form">
                <span class="font-medium">Forma:</span> {{ item.pharmaceutical_form }}
              </span>
              <span v-if="item.quantity">
                <span class="font-medium">Qtd.:</span> {{ item.quantity }}
              </span>
              <span v-if="item.treatment_duration">
                <span class="font-medium">Duração:</span> {{ item.treatment_duration }}
              </span>
            </div>
            <div>
              <p class="text-xs font-medium text-foreground-muted mb-0.5">Posologia</p>
              <p class="text-sm text-foreground whitespace-pre-wrap">{{ item.posology }}</p>
            </div>
          </div>
        </div>

        <!-- Observações -->
        <div class="rounded-xl border border-border bg-surface p-5 mb-4 space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-foreground">Observações</h2>
            <button
              v-if="!editingNotes && !prescription.masked"
              type="button"
              class="text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              @click="startEditNotes"
            >
              Editar observações
            </button>
          </div>

          <!-- View mode -->
          <div v-if="!editingNotes">
            <p
              v-if="prescription.masked"
              class="text-sm text-foreground-muted tracking-widest"
            >
              •••
            </p>
            <p
              v-else-if="prescription.notes"
              class="text-sm text-foreground whitespace-pre-wrap"
            >
              {{ prescription.notes }}
            </p>
            <p v-else class="text-sm text-foreground-muted italic">
              Sem observações.
            </p>
          </div>

          <!-- Edit mode -->
          <div v-else class="space-y-3">
            <textarea
              v-model="notesEdit"
              rows="4"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 resize-none"
              placeholder="Observações sobre a receita..."
              aria-label="Observações da receita"
              autofocus
            />
            <p v-if="notesError" role="alert" class="text-xs text-danger-500">{{ notesError }}</p>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-foreground hover:bg-surface-elevated focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                @click="cancelEditNotes"
              >
                Cancelar
              </button>
              <button
                type="button"
                :disabled="savingNotes"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-60 transition"
                @click="saveNotes"
              >
                <svg v-if="savingNotes" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Salvar
              </button>
            </div>
          </div>
        </div>

        <!-- PDF Uploader -->
        <div class="rounded-xl border border-border bg-surface p-5 mb-4">
          <PrescriptionPdfUploader :prescription="prescription" />
        </div>

        <!-- Alertas de vencimento (T115/T116) -->
        <div class="rounded-xl border border-border bg-surface p-5 mb-4">
          <PrescriptionAlertConfigToggle
            :prescription="prescription"
            @updated="(updated) => { store.current = updated }"
          />
        </div>

        <!-- Timeline simplificada -->
        <!-- TODO US-8.1: Histórico completo via EventoTimeline (Fase 2) — dados completos disponíveis na integração de timeline do paciente -->
        <div class="rounded-xl border border-border bg-surface p-5">
          <h2 class="text-sm font-semibold text-foreground mb-3">Histórico</h2>
          <ol
            v-if="timelineEvents.length"
            class="relative border-l border-border pl-4 space-y-3"
            aria-label="Histórico da receita"
          >
            <li
              v-for="event in timelineEvents"
              :key="event.key"
              class="relative"
            >
              <!-- Ponto -->
              <span
                class="absolute -left-[1.15rem] mt-0.5 flex h-3 w-3 items-center justify-center rounded-full ring-2 ring-surface"
                :class="event.classes.includes('success') ? 'bg-success-500' : event.classes.includes('danger') ? 'bg-danger-500' : 'bg-foreground-muted'"
                aria-hidden="true"
              />
              <p class="text-sm font-medium text-foreground">{{ event.label }}</p>
              <p class="text-xs text-foreground-muted">{{ fmtDateTime(event.date) }}</p>
            </li>
          </ol>
          <p v-else class="text-sm text-foreground-muted italic">Sem eventos registrados.</p>
        </div>
      </template>

      <!-- Toast -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="toast"
          role="alert"
          aria-live="assertive"
          class="fixed bottom-4 right-4 z-50 flex items-center gap-2 rounded-lg px-4 py-3 text-sm shadow-lg"
          :class="[
            toast.type === 'error'
              ? 'bg-danger-50 text-danger-700 ring-1 ring-danger-200'
              : 'bg-success-50 text-success-700 ring-1 ring-success-200',
          ]"
        >
          {{ toast.message }}
        </div>
      </Transition>

      <!-- Modal cancelamento -->
      <PrescriptionCancelModal
        v-model="showCancelModal"
        :prescription="prescription"
        @cancelled="onCancelled"
      />
    </div>
  </div>
</template>

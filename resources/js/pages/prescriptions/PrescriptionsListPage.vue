<script setup>
/**
 * T082 (Fase 7) — Listagem paginada de receituários (US-8.1).
 *
 * Features:
 *  - Paginação cursor-based (next_cursor).
 *  - Filtros via usePrescriptionFilters: status, type, paciente (autocomplete), profissional, datas.
 *  - Tabela responsiva com skeleton, empty, error + retry.
 *  - Receitas mascaradas: placeholder "•••" + ControlledMaskingBanner.
 *  - Atalhos: "n" → criar, "/" → foco no filtro de busca.
 *  - Colunas: paciente, profissional, tipo, status, validade, criticidade, ações.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { DateTime } from 'luxon'
import { usePrescriptionsStore } from '@/stores/prescriptionsStore'
import { useAuthStore } from '@/stores/auth'
import { usePrescriptionFilters } from '@/composables/usePrescriptionFilters'
import api from '@/lib/api'
import PrescriptionTypeBadge from '@/components/prescriptions/PrescriptionTypeBadge.vue'
import PrescriptionStatusPill from '@/components/prescriptions/PrescriptionStatusPill.vue'
import ControlledMaskingBanner from '@/components/prescriptions/ControlledMaskingBanner.vue'
import PrescriptionCancelModal from '@/components/prescriptions/PrescriptionCancelModal.vue'

const router = useRouter()
const store = usePrescriptionsStore()
const auth = useAuthStore()
const { filters, applyFilter, clearFilters, activeFilterCount } = usePrescriptionFilters()

// ─── Estado local ─────────────────────────────────────────────────────────────

const professionals = ref([])
const patientQuery = ref('')
const patientResults = ref([])
const patientSearchLoading = ref(false)
const showCancelModal = ref(false)
const prescriptionToCancel = ref(null)
const searchInputRef = ref(null)

let patientSearchTimer = null

// ─── Toast local (padrão Fase 6) ─────────────────────────────────────────────

const toast = ref(null)
let toastTimer = null

function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => { toast.value = null }, 5000)
}

// ─── Criticidade ──────────────────────────────────────────────────────────────

function criticalityOf(prescription) {
  if (prescription.status !== 'active') return null
  const diff = Math.ceil(
    (new Date(prescription.expires_at) - new Date()) / 86400000,
  )
  if (diff > 30) return 'green'
  if (diff > 7) return 'yellow'
  return 'red'
}

const CRITICALITY_LABEL = {
  green: 'Válida',
  yellow: 'Vence em breve',
  red: 'Crítica ou vencida',
}

const CRITICALITY_CLASSES = {
  green: 'text-success-600',
  yellow: 'text-warning-600',
  red: 'text-danger-600',
}

// ─── Carregamento ─────────────────────────────────────────────────────────────

async function load(cursor = null) {
  await store.fetchList({ ...filters.value, cursor })
}

async function loadProfessionals() {
  try {
    const { data } = await api.get('/agenda/professionals', { params: { per_page: 100 } })
    professionals.value = data.data ?? []
  } catch {
    // não crítico — filtro de profissional apenas não será populado
  }
}

async function loadMore() {
  if (!store.pagination.next_cursor) return
  await store.fetchList({ ...filters.value, cursor: store.pagination.next_cursor })
  // Append ao invés de substituir
}

// ─── Busca de paciente (autocomplete) ────────────────────────────────────────

watch(patientQuery, (v) => {
  clearTimeout(patientSearchTimer)
  if (!v || v.length < 2) {
    patientResults.value = []
    return
  }
  patientSearchTimer = setTimeout(async () => {
    patientSearchLoading.value = true
    try {
      const { data } = await api.get('/pacientes', { params: { search: v, per_page: 8 } })
      patientResults.value = data.data ?? []
    } finally {
      patientSearchLoading.value = false
    }
  }, 250)
})

function selectPatient(patient) {
  applyFilter('patient_id', patient.id)
  patientQuery.value = patient.nome
  patientResults.value = []
}

function clearPatient() {
  applyFilter('patient_id', null)
  patientQuery.value = ''
  patientResults.value = []
}

// ─── Watch filtros → refetch ──────────────────────────────────────────────────

watch(filters, () => load(), { deep: true })

// ─── Atalhos de teclado ───────────────────────────────────────────────────────

function handleKeydown(e) {
  // Ignora quando o foco está em input/textarea/select
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return

  if (e.key === 'n') {
    e.preventDefault()
    router.push({ name: 'prescriptions.create' })
  }
  if (e.key === '/') {
    e.preventDefault()
    searchInputRef.value?.focus()
  }
}

onMounted(() => {
  load()
  loadProfessionals()
  window.addEventListener('keydown', handleKeydown)
  // T117 — subscribe ao canal Reverb para refresh de alertas em tempo real
  store.subscribeToReportRefresh(auth.currentTenantId)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  // T117 — unsubscribe do canal Reverb
  store.unsubscribeFromReportRefresh(auth.currentTenantId)
})

// ─── Cancelamento ─────────────────────────────────────────────────────────────

function openCancel(prescription) {
  prescriptionToCancel.value = prescription
  showCancelModal.value = true
}

function onCancelled() {
  showToast('Receita cancelada.')
  load()
}

// ─── Formatação pt-BR ─────────────────────────────────────────────────────────

function fmtDate(iso) {
  if (!iso) return '—'
  return DateTime.fromISO(iso).setLocale('pt-BR').toFormat('dd/MM/yyyy')
}

function fmtRelative(iso) {
  if (!iso) return ''
  return DateTime.fromISO(iso).toRelative({ locale: 'pt-BR' })
}
</script>

<template>
  <div class="min-h-screen bg-background">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

      <!-- Cabeçalho -->
      <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-xl font-semibold text-foreground">Receituários</h1>
          <p class="text-sm text-foreground-muted mt-0.5">
            Pressione <kbd class="rounded bg-surface-elevated px-1 py-0.5 text-xs ring-1 ring-border">n</kbd> para criar,
            <kbd class="rounded bg-surface-elevated px-1 py-0.5 text-xs ring-1 ring-border">/</kbd> para buscar
          </p>
        </div>
        <router-link
          :to="{ name: 'prescriptions.create' }"
          class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Nova Receita
        </router-link>
      </div>

      <!-- Filtros -->
      <div class="mb-4 rounded-xl border border-border bg-surface p-4 space-y-3">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">

          <!-- Status -->
          <div>
            <label for="filter-status" class="block text-xs font-medium text-foreground mb-1">Status</label>
            <select
              id="filter-status"
              :value="filters.status"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              @change="applyFilter('status', $event.target.value || null)"
            >
              <option value="">Todos</option>
              <option value="active">Ativa</option>
              <option value="cancelled">Cancelada</option>
              <option value="superseded">Substituída</option>
            </select>
          </div>

          <!-- Tipo -->
          <div>
            <label for="filter-type" class="block text-xs font-medium text-foreground mb-1">Tipo</label>
            <select
              id="filter-type"
              :value="filters.type"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              @change="applyFilter('type', $event.target.value || null)"
            >
              <option value="">Todos</option>
              <option value="common">Comum</option>
              <option value="special">Especial</option>
              <option value="controlled">Controlada</option>
            </select>
          </div>

          <!-- Paciente autocomplete -->
          <div class="relative">
            <label for="filter-patient" class="block text-xs font-medium text-foreground mb-1">Paciente</label>
            <div class="relative">
              <input
                id="filter-patient"
                ref="searchInputRef"
                v-model="patientQuery"
                type="text"
                placeholder="Buscar paciente..."
                autocomplete="off"
                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              />
              <button
                v-if="filters.patient_id"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                aria-label="Limpar filtro de paciente"
                @click="clearPatient"
              >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <ul
              v-if="patientResults.length"
              class="absolute z-10 mt-1 w-full rounded-lg border border-border bg-surface shadow-lg max-h-48 overflow-auto"
              role="listbox"
              aria-label="Sugestões de pacientes"
            >
              <li
                v-for="p in patientResults"
                :key="p.id"
                role="option"
                class="cursor-pointer px-3 py-2 text-sm text-foreground hover:bg-primary-50"
                @click="selectPatient(p)"
              >
                {{ p.nome }}
                <span class="ml-1 text-xs text-foreground-muted">{{ p.cpf || p.telefone_primario }}</span>
              </li>
            </ul>
          </div>

          <!-- Profissional -->
          <div>
            <label for="filter-professional" class="block text-xs font-medium text-foreground mb-1">Profissional</label>
            <select
              id="filter-professional"
              :value="filters.professional_id"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              @change="applyFilter('professional_id', $event.target.value || null)"
            >
              <option value="">Todos</option>
              <option v-for="p in professionals" :key="p.id" :value="p.id">
                {{ p.name ?? p.nome }}
              </option>
            </select>
          </div>

          <!-- Vence após -->
          <div>
            <label for="filter-after" class="block text-xs font-medium text-foreground mb-1">Vence após</label>
            <input
              id="filter-after"
              type="date"
              :value="filters.expires_after"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              @change="applyFilter('expires_after', $event.target.value || null)"
            />
          </div>

          <!-- Vence antes -->
          <div>
            <label for="filter-before" class="block text-xs font-medium text-foreground mb-1">Vence antes</label>
            <input
              id="filter-before"
              type="date"
              :value="filters.expires_before"
              class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
              @change="applyFilter('expires_before', $event.target.value || null)"
            />
          </div>
        </div>

        <!-- Limpar filtros -->
        <div v-if="activeFilterCount > 0" class="flex justify-end">
          <button
            type="button"
            class="text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
            @click="clearFilters"
          >
            Limpar filtros ({{ activeFilterCount }})
          </button>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="store.loading && store.list.length === 0" aria-busy="true" aria-label="Carregando receituários" class="space-y-2">
        <div v-for="i in 5" :key="i" class="h-16 rounded-xl bg-surface-elevated animate-pulse" />
      </div>

      <!-- Error state -->
      <div v-else-if="store.error && store.list.length === 0" class="rounded-xl border border-border bg-surface p-8 text-center">
        <p class="text-sm text-danger-600 mb-3">{{ store.error }}</p>
        <button
          type="button"
          class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
          @click="load()"
        >
          Tentar novamente
        </button>
      </div>

      <!-- Empty state -->
      <div
        v-else-if="!store.loading && store.list.length === 0"
        class="rounded-xl border border-dashed border-border p-12 text-center"
      >
        <svg class="mx-auto h-10 w-10 text-foreground-muted mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <p class="text-sm text-foreground-muted mb-4">Nenhuma receita encontrada.</p>
        <router-link
          :to="{ name: 'prescriptions.create' }"
          class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
        >
          Nova receita
        </router-link>
      </div>

      <!-- Tabela de receitas -->
      <div v-else class="overflow-hidden rounded-xl border border-border bg-surface">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-elevated">
              <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide">Paciente</th>
                <th scope="col" class="hidden px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide sm:table-cell">Profissional</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide">Tipo</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide">Status</th>
                <th scope="col" class="hidden px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide md:table-cell">Validade</th>
                <th scope="col" class="hidden px-4 py-3 text-left text-xs font-semibold text-foreground-muted uppercase tracking-wide lg:table-cell">Criticidade</th>
                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-foreground-muted uppercase tracking-wide">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="prescription in store.list"
                :key="prescription.id"
                class="hover:bg-surface-elevated transition"
              >
                <!-- Paciente -->
                <td class="px-4 py-3 font-medium text-foreground">
                  <span v-if="prescription.masked" class="text-foreground-muted tracking-widest" aria-label="Conteúdo restrito">•••</span>
                  <span v-else>{{ prescription.patient_name ?? `Paciente #${prescription.patient_id}` }}</span>
                </td>

                <!-- Profissional -->
                <td class="hidden px-4 py-3 text-foreground-muted sm:table-cell">
                  {{ prescription.professional_name ?? '—' }}
                </td>

                <!-- Tipo -->
                <td class="px-4 py-3">
                  <PrescriptionTypeBadge :type="prescription.type" />
                </td>

                <!-- Status -->
                <td class="px-4 py-3">
                  <PrescriptionStatusPill :status="prescription.status" />
                </td>

                <!-- Validade -->
                <td class="hidden px-4 py-3 text-foreground-muted md:table-cell">
                  <span v-if="prescription.masked" class="tracking-widest">•••</span>
                  <span v-else>
                    {{ fmtDate(prescription.expires_at) }}
                    <span class="block text-xs">{{ fmtRelative(prescription.expires_at) }}</span>
                  </span>
                </td>

                <!-- Criticidade -->
                <td class="hidden px-4 py-3 lg:table-cell">
                  <span
                    v-if="criticalityOf(prescription)"
                    class="inline-flex items-center gap-1 text-xs font-medium"
                    :class="CRITICALITY_CLASSES[criticalityOf(prescription)]"
                    :title="CRITICALITY_LABEL[criticalityOf(prescription)]"
                  >
                    <svg class="h-3 w-3" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true">
                      <circle cx="4" cy="4" r="4" />
                    </svg>
                    {{ CRITICALITY_LABEL[criticalityOf(prescription)] }}
                  </span>
                  <span v-else class="text-xs text-foreground-muted">—</span>
                </td>

                <!-- Ações -->
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="{ name: 'prescriptions.show', params: { id: prescription.id } }"
                      class="rounded px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 transition"
                    >
                      Ver
                    </router-link>

                    <!-- Renovar — placeholder US-8.3 -->
                    <!-- TODO US-8.3: Renovação via IA — disponível em Lote D -->
                    <span
                      class="cursor-not-allowed rounded px-2 py-1 text-xs font-medium text-foreground-muted"
                      title="Disponível em breve"
                    >
                      Renovar
                    </span>

                    <button
                      v-if="prescription.status === 'active' && !prescription.masked"
                      type="button"
                      class="rounded px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500 transition"
                      @click="openCancel(prescription)"
                    >
                      Cancelar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mascaramento banner (se alguma linha for mascarada) -->
        <div
          v-if="store.list.some((p) => p.masked)"
          class="border-t border-border px-4 py-3"
        >
          <ControlledMaskingBanner :masked="true" />
        </div>

        <!-- Paginação cursor -->
        <div
          v-if="store.pagination.next_cursor"
          class="border-t border-border px-4 py-3 text-center"
        >
          <button
            type="button"
            :disabled="store.loading"
            class="rounded-lg px-4 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:opacity-50 transition"
            @click="loadMore"
          >
            {{ store.loading ? 'Carregando...' : 'Carregar mais' }}
          </button>
        </div>
      </div>

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
        :prescription="prescriptionToCancel"
        @cancelled="onCancelled"
      />
    </div>
  </div>
</template>

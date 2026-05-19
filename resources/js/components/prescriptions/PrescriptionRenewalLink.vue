<script setup>
/**
 * T145 (Fase 7 Lote D) — Card reutilizável que exibe a ligação entre uma consulta
 * e a renovação de receita associada.
 *
 * TODO: integrar em AgendaPage.vue / consulta detail quando a UX de receita-na-agenda
 * for desenhada (Fase 7 Lote E ou Fase 8).
 *
 * Props:
 *  - appointment (Object, required) — objeto da consulta; pode conter prescription_id.
 *  - original (Object, optional) — se já fornecido, evita request adicional.
 *
 * Quando `original` não é fornecido: tenta buscar via prescriptionsApi.getAppointmentRenewal.
 * O método é placeholder (retorna null) pois não há endpoint específico ainda.
 */
import { ref, computed, onMounted } from 'vue'
import { DateTime } from 'luxon'
import { getAppointmentRenewal } from '@/lib/prescriptionsApi'

const props = defineProps({
  appointment: {
    type: Object,
    required: true,
  },
  original: {
    type: Object,
    default: null,
  },
})

// ─── Estado ───────────────────────────────────────────────────────────────────

const prescription = ref(props.original ?? null)
const loading = ref(false)

// ─── Carregamento lazy se `original` não fornecido ────────────────────────────

onMounted(async () => {
  if (prescription.value) return
  if (!props.appointment?.id) return

  loading.value = true
  try {
    // TODO: endpoint GET /api/v1/appointments/{id}/renewal não existe ainda.
    // getAppointmentRenewal é um placeholder que retorna null.
    prescription.value = await getAppointmentRenewal(props.appointment.id)
  } catch {
    // Silencioso — card simplesmente não renderiza se dados indisponíveis
    prescription.value = null
  } finally {
    loading.value = false
  }
})

// ─── Formatação ───────────────────────────────────────────────────────────────

function fmtDate(iso) {
  if (!iso) return '—'
  return DateTime.fromISO(iso).toFormat('dd/MM/yyyy')
}

// ─── Status da renovação ──────────────────────────────────────────────────────

/**
 * Verifica se a renovação já foi completada.
 * `renewed_to_id` presente indica que a receita original foi superseded e há uma nova.
 * `renewed_at` indica quando ocorreu.
 */
const renewalCompleted = computed(() => {
  return !!(prescription.value?.renewed_to_id || prescription.value?.renewed_at)
})

const renewedAtDisplay = computed(() => {
  const iso = prescription.value?.renewed_at
  if (!iso) return null
  return DateTime.fromISO(iso).toFormat('dd/MM')
})

// ─── ID para aria-labelledby ──────────────────────────────────────────────────

const labelId = `renewal-link-label-${props.appointment.id ?? Math.random().toString(36).slice(2)}`
</script>

<template>
  <!-- Não renderiza se não houver dados e não estiver carregando -->
  <div
    v-if="loading || prescription"
    role="region"
    :aria-labelledby="labelId"
    class="rounded-xl border border-border bg-surface p-4"
  >
    <!-- Loading state -->
    <div v-if="loading" class="flex items-center gap-3" aria-busy="true" aria-label="Carregando informações de renovação">
      <div class="h-8 w-8 rounded-lg bg-surface-elevated animate-pulse shrink-0" />
      <div class="flex-1 space-y-2">
        <div class="h-3 w-32 rounded bg-surface-elevated animate-pulse" />
        <div class="h-3 w-48 rounded bg-surface-elevated animate-pulse" />
      </div>
    </div>

    <!-- Card de renovação -->
    <div v-else-if="prescription" class="flex items-start gap-3">
      <!-- Ícone -->
      <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 ring-1 ring-primary-200">
        <svg class="h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
      </div>

      <!-- Conteúdo -->
      <div class="flex-1 min-w-0">
        <p :id="labelId" class="text-sm font-medium text-foreground">
          Renovação de receita
          <span class="font-normal text-foreground-muted">
            — ref. Receita #{{ prescription.id }}
            <template v-if="prescription.issued_at">
              de {{ fmtDate(prescription.issued_at) }}
            </template>
          </span>
        </p>

        <!-- Badges de status -->
        <div class="mt-1.5 flex flex-wrap items-center gap-2">
          <span
            v-if="renewalCompleted"
            class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 ring-1 ring-success-200"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            Renovação concluída{{ renewedAtDisplay ? ` em ${renewedAtDisplay}` : '' }}
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 ring-1 ring-warning-200"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Aguardando emissão
          </span>
        </div>

        <!-- Link para ver receita original -->
        <router-link
          :to="{ name: 'prescriptions.show', params: { id: prescription.id } }"
          class="mt-2 inline-block text-xs text-primary-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 rounded"
        >
          Ver receita original
        </router-link>
      </div>
    </div>
  </div>
</template>

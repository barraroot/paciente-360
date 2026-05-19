/**
 * T077 (Fase 7) — Composable de filtros para o módulo de Receituários (US-8.1).
 *
 * Expõe `filters` ref + helpers para aplicar/limpar filtros.
 * Intencionalmente simples (sem sync de URL) para reuso em contextos
 * embedded (ex.: tab no perfil do paciente) e de página standalone.
 */
import { ref, computed } from 'vue'

const DEFAULT_FILTERS = () => ({
  status: null,
  type: null,
  patient_id: null,
  professional_id: null,
  expires_after: null,
  expires_before: null,
})

export function usePrescriptionFilters() {
  const filters = ref(DEFAULT_FILTERS())

  /**
   * Aplica um único filtro.
   * @param {string} key
   * @param {any} value - null para remover
   */
  function applyFilter(key, value) {
    filters.value[key] = value ?? null
  }

  /**
   * Limpa todos os filtros para o estado inicial.
   */
  function clearFilters() {
    filters.value = DEFAULT_FILTERS()
  }

  /**
   * Número de filtros ativos (não-nulos).
   * @type {import('vue').ComputedRef<number>}
   */
  const activeFilterCount = computed(() =>
    Object.values(filters.value).filter(
      (v) => v !== null && v !== undefined && v !== '',
    ).length,
  )

  return {
    filters,
    applyFilter,
    clearFilters,
    activeFilterCount,
  }
}

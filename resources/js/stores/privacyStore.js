/**
 * **T034 (Fase 8 — Lote A US-13.1)** — Pinia store do módulo Privacidade.
 *
 * Estado e ações de consentimento granular. Setup syntax (defineStore com
 * factory) consistente com `prescriptionsStore.js` (Fase 7).
 */
import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import {
  listConsents,
  getConsent,
  recordConsent,
  revokeConsent,
} from '@/lib/privacyApi'

export const usePrivacyStore = defineStore('privacy', () => {
  // ─── State ─────────────────────────────────────────────────────────────────

  const consents = ref([])
  const consentsPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
  })
  const currentConsent = ref(null)
  const filters = ref({
    patient_id: null,
    finalidade: null,
    state: null,
    channel: null,
  })
  const loading = ref(false)
  const saving = ref(false)
  const error = ref(null)

  // ─── Getters ───────────────────────────────────────────────────────────────

  const activeConsents = computed(() =>
    consents.value.filter((c) => c.is_active)
  )

  const revokedCount = computed(() =>
    consents.value.filter((c) => c.state === 'revoked').length
  )

  // ─── Actions ───────────────────────────────────────────────────────────────

  /**
   * Carrega lista de consentimentos do tenant atual.
   * @param {Object} extraFilters - Sobrescreve filters() temporariamente.
   */
  async function fetchConsents(extraFilters = {}) {
    loading.value = true
    error.value = null

    try {
      const params = { ...filters.value, ...extraFilters }
      // Remove keys com valor null/undefined para evitar query string poluído.
      Object.keys(params).forEach((k) => {
        if (params[k] === null || params[k] === undefined || params[k] === '') {
          delete params[k]
        }
      })

      const response = await listConsents(params)
      consents.value = response.data.data
      consentsPagination.value = {
        current_page: response.data.meta?.current_page ?? 1,
        last_page: response.data.meta?.last_page ?? 1,
        per_page: response.data.meta?.per_page ?? 25,
        total: response.data.meta?.total ?? consents.value.length,
      }
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao carregar consentimentos.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchConsent(id) {
    loading.value = true
    error.value = null

    try {
      const response = await getConsent(id)
      currentConsent.value = response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao carregar consentimento.'
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Registra um consentimento (granted ou refused).
   * @param {Object} payload - patient_id, channel, finalidade, state, evidence_*, terms_version
   */
  async function createConsent(payload) {
    saving.value = true
    error.value = null

    try {
      const response = await recordConsent(payload)
      // Re-carrega lista para refletir o novo consentimento.
      await fetchConsents()
      return response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao registrar consentimento.'
      throw e
    } finally {
      saving.value = false
    }
  }

  /**
   * Revoga consentimento por (paciente, finalidade).
   * @param {Object} payload - patient_id, finalidade, channel, scope, evidence_message_id?
   */
  async function revoke(payload) {
    saving.value = true
    error.value = null

    try {
      const response = await revokeConsent(payload)
      await fetchConsents()
      return response.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao revogar consentimento.'
      throw e
    } finally {
      saving.value = false
    }
  }

  function setFilter(key, value) {
    filters.value[key] = value
  }

  function resetFilters() {
    filters.value = {
      patient_id: null,
      finalidade: null,
      state: null,
      channel: null,
    }
  }

  return {
    // state
    consents,
    consentsPagination,
    currentConsent,
    filters,
    loading,
    saving,
    error,
    // getters
    activeConsents,
    revokedCount,
    // actions
    fetchConsents,
    fetchConsent,
    createConsent,
    revoke,
    setFilter,
    resetFilters,
  }
})

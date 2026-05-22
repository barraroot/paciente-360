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
  listForgettingRequests,
  getForgettingRequest,
  createForgettingRequest,
  executeForgettingRequest,
  denyForgettingRequest,
  listPortabilityRequests,
  getPortabilityRequest,
  createPortabilityRequest,
  executePortabilityRequest,
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

  // ─── Forgetting Requests (US-13.2) ─────────────────────────────────────

  const forgettingRequests = ref([])
  const forgettingPagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const currentForgetting = ref(null)

  async function fetchForgettingRequests(extraFilters = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await listForgettingRequests(extraFilters)
      forgettingRequests.value = response.data.data
      forgettingPagination.value = {
        current_page: response.data.meta?.current_page ?? 1,
        last_page: response.data.meta?.last_page ?? 1,
        total: response.data.meta?.total ?? forgettingRequests.value.length,
      }
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao carregar solicitações de esquecimento.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchForgetting(id) {
    loading.value = true
    try {
      const response = await getForgettingRequest(id)
      currentForgetting.value = response.data.data
    } finally {
      loading.value = false
    }
  }

  async function submitForgetting(payload) {
    saving.value = true
    error.value = null
    try {
      const response = await createForgettingRequest(payload)
      await fetchForgettingRequests({ only_open: true })
      return response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao criar solicitação.'
      throw e
    } finally {
      saving.value = false
    }
  }

  async function executeForgetting(id) {
    saving.value = true
    error.value = null
    try {
      const response = await executeForgettingRequest(id, { confirmation: true })
      await fetchForgettingRequests({ only_open: true })
      return response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao executar esquecimento.'
      throw e
    } finally {
      saving.value = false
    }
  }

  async function denyForgetting(id, reason) {
    saving.value = true
    error.value = null
    try {
      const response = await denyForgettingRequest(id, { reason })
      await fetchForgettingRequests({ only_open: true })
      return response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao negar solicitação.'
      throw e
    } finally {
      saving.value = false
    }
  }

  // ─── Portability Requests (US-13.2 — Q28) ──────────────────────────────

  const portabilityRequests = ref([])
  const portabilityPagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const currentPortability = ref(null)

  async function fetchPortabilityRequests(extraFilters = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await listPortabilityRequests(extraFilters)
      portabilityRequests.value = response.data.data
      portabilityPagination.value = {
        current_page: response.data.meta?.current_page ?? 1,
        last_page: response.data.meta?.last_page ?? 1,
        total: response.data.meta?.total ?? portabilityRequests.value.length,
      }
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao carregar solicitações de portabilidade.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function submitPortability(payload) {
    saving.value = true
    error.value = null
    try {
      const response = await createPortabilityRequest(payload)
      await fetchPortabilityRequests()
      return response.data.data
    } finally {
      saving.value = false
    }
  }

  async function executePortability(id) {
    saving.value = true
    error.value = null
    try {
      const response = await executePortabilityRequest(id)
      await fetchPortabilityRequests()
      return response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao gerar arquivo de portabilidade.'
      throw e
    } finally {
      saving.value = false
    }
  }

  return {
    // ─── consents (US-13.1) ───
    consents,
    consentsPagination,
    currentConsent,
    filters,
    loading,
    saving,
    error,
    activeConsents,
    revokedCount,
    fetchConsents,
    fetchConsent,
    createConsent,
    revoke,
    setFilter,
    resetFilters,
    // ─── forgetting (US-13.2) ───
    forgettingRequests,
    forgettingPagination,
    currentForgetting,
    fetchForgettingRequests,
    fetchForgetting,
    submitForgetting,
    executeForgetting,
    denyForgetting,
    // ─── portability (US-13.2 — Q28) ───
    portabilityRequests,
    portabilityPagination,
    currentPortability,
    fetchPortabilityRequests,
    submitPortability,
    executePortability,
  }
})

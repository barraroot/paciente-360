/**
 * **T170 (Fase 8 — Lote C US-9.1)** — Pinia store de Campanhas.
 *
 * State + actions seguindo padrão `prescriptionsStore`/`privacyStore`.
 * Polling 30s (Q6) implementado pela CampaignReportPage diretamente
 * (`setInterval` + cleanup em `onUnmounted`).
 */
import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import {
  listCampaigns,
  getCampaign,
  createCampaign,
  updateCampaign,
  previewCampaign,
  dispatchCampaign,
  cancelCampaign,
  getCampaignReport,
} from '@/lib/campaignsApi'

export const useCampaignsStore = defineStore('campaigns', () => {
  const campaigns = ref([])
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const current = ref(null)
  const report = ref(null)
  const filters = ref({ status: null, channel: null })
  const loading = ref(false)
  const saving = ref(false)
  const error = ref(null)

  const drafts = computed(() => campaigns.value.filter((c) => c.status === 'draft'))
  const scheduled = computed(() => campaigns.value.filter((c) => c.status === 'scheduled'))
  const dispatching = computed(() => campaigns.value.filter((c) => c.status === 'dispatching'))

  async function fetchCampaigns(extra = {}) {
    loading.value = true
    error.value = null
    try {
      const params = { ...filters.value, ...extra }
      Object.keys(params).forEach((k) => {
        if (params[k] === null || params[k] === '') delete params[k]
      })

      const response = await listCampaigns(params)
      campaigns.value = response.data.data
      pagination.value = {
        current_page: response.data.meta?.current_page ?? 1,
        last_page: response.data.meta?.last_page ?? 1,
        total: response.data.meta?.total ?? campaigns.value.length,
      }
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao carregar campanhas.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchCampaign(id) {
    loading.value = true
    try {
      const response = await getCampaign(id)
      current.value = response.data.data
    } finally {
      loading.value = false
    }
  }

  async function create(payload) {
    saving.value = true
    error.value = null
    try {
      const response = await createCampaign(payload)
      await fetchCampaigns()
      return response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao criar campanha.'
      throw e
    } finally {
      saving.value = false
    }
  }

  async function update(id, payload) {
    saving.value = true
    try {
      const response = await updateCampaign(id, payload)
      current.value = response.data.data
      return response.data.data
    } finally {
      saving.value = false
    }
  }

  async function preview(id) {
    return (await previewCampaign(id)).data
  }

  async function dispatch(id) {
    saving.value = true
    error.value = null
    try {
      const response = await dispatchCampaign(id)
      await fetchCampaigns()
      return response.data.data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao disparar campanha.'
      throw e
    } finally {
      saving.value = false
    }
  }

  async function cancel(id, reason = null) {
    saving.value = true
    try {
      const response = await cancelCampaign(id, reason)
      await fetchCampaigns()
      return response.data.data
    } finally {
      saving.value = false
    }
  }

  async function fetchReport(id) {
    const response = await getCampaignReport(id)
    report.value = response.data.data
    return report.value
  }

  function setFilter(key, value) {
    filters.value[key] = value
  }

  function resetFilters() {
    filters.value = { status: null, channel: null }
  }

  return {
    // state
    campaigns,
    pagination,
    current,
    report,
    filters,
    loading,
    saving,
    error,
    // getters
    drafts,
    scheduled,
    dispatching,
    // actions
    fetchCampaigns,
    fetchCampaign,
    create,
    update,
    preview,
    dispatch,
    cancel,
    fetchReport,
    setFilter,
    resetFilters,
  }
})

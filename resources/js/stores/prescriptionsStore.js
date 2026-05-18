/**
 * T076 (Fase 7) — Pinia store do módulo de Gestão de Receituários (US-8.1).
 *
 * Setup syntax (defineStore) — Fase 5/6 padrão.
 */
import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import {
  listPrescriptions,
  getPrescription,
  createPrescription,
  updateNotes,
  cancelPrescription,
  uploadPdf,
  downloadPdf,
} from '@/lib/prescriptionsApi'

export const usePrescriptionsStore = defineStore('prescriptions', () => {
  // ─── State ─────────────────────────────────────────────────────────────────

  const list = ref([])
  const pagination = ref({
    next_cursor: null,
    prev_cursor: null,
    per_page: 20,
    total: null,
  })
  const current = ref(null)
  const filters = ref({
    status: null,
    type: null,
    patient_id: null,
    professional_id: null,
    expires_after: null,
    expires_before: null,
    cursor: null,
    per_page: 20,
  })
  const loading = ref(false)
  const error = ref(null)
  const uploading = ref(false)

  // ─── Getters ───────────────────────────────────────────────────────────────

  /**
   * Busca receita pelo id no cache local.
   * @param {number} id
   */
  const byId = computed(() => (id) => list.value.find((p) => p.id === id) ?? null)

  /**
   * Filtra receitas por tipo no cache local.
   * @param {'common'|'special'|'controlled'} type
   */
  const byType = computed(() => (type) => list.value.filter((p) => p.type === type))

  /**
   * Conta receitas por criticidade (calculada pelo campo expires_at).
   * Verde: > 30d | Amarelo: 8-30d | Vermelho: 0-7d / vencida.
   */
  const criticalityCount = computed(() => {
    const now = new Date()
    return list.value.reduce(
      (acc, p) => {
        if (p.status !== 'active') return acc
        const diff = Math.ceil((new Date(p.expires_at) - now) / 86400000)
        if (diff > 30) acc.green++
        else if (diff > 7) acc.yellow++
        else acc.red++
        return acc
      },
      { green: 0, yellow: 0, red: 0 },
    )
  })

  // ─── Actions ───────────────────────────────────────────────────────────────

  async function fetchList(overrideFilters = {}) {
    loading.value = true
    error.value = null
    try {
      const params = { ...filters.value, ...overrideFilters }
      // Remove chaves nulas/vazias para não poluir a query string
      Object.keys(params).forEach((k) => {
        if (params[k] === null || params[k] === undefined || params[k] === '') {
          delete params[k]
        }
      })
      const { data } = await listPrescriptions(params)
      list.value = data.data ?? []
      pagination.value = {
        next_cursor: data.meta?.next_cursor ?? null,
        prev_cursor: data.meta?.prev_cursor ?? null,
        per_page: data.meta?.per_page ?? 20,
        total: data.meta?.total ?? null,
      }
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao carregar receituários.'
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id) {
    loading.value = true
    error.value = null
    try {
      const { data } = await getPrescription(id)
      current.value = data.data ?? data
      // Atualiza o item no cache de lista se existir
      const idx = list.value.findIndex((p) => p.id === Number(id))
      if (idx >= 0) list.value[idx] = current.value
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Receituário não encontrado.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function create(payload) {
    loading.value = true
    error.value = null
    try {
      const { data } = await createPrescription(payload)
      const created = data.data ?? data
      list.value.unshift(created)
      current.value = created
      return created
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao criar receituário.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function updateNotesAction(id, notes) {
    try {
      const { data } = await updateNotes(id, notes)
      const updated = data.data ?? data
      current.value = updated
      const idx = list.value.findIndex((p) => p.id === Number(id))
      if (idx >= 0) list.value[idx] = updated
      return updated
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao atualizar observações.'
      throw e
    }
  }

  async function cancel(id, { category, reason }) {
    try {
      const { data } = await cancelPrescription(id, { category, reason })
      const updated = data.data ?? data
      current.value = updated
      const idx = list.value.findIndex((p) => p.id === Number(id))
      if (idx >= 0) list.value[idx] = updated
      return updated
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao cancelar receituário.'
      throw e
    }
  }

  async function uploadPdfAction(id, file, onProgress) {
    uploading.value = true
    try {
      const { data } = await uploadPdf(id, file, onProgress)
      const updated = data.data ?? data
      if (current.value?.id === Number(id)) current.value = updated
      const idx = list.value.findIndex((p) => p.id === Number(id))
      if (idx >= 0) list.value[idx] = updated
      return updated
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao enviar PDF.'
      throw e
    } finally {
      uploading.value = false
    }
  }

  async function requestDownloadUrl(id) {
    try {
      const { data } = await downloadPdf(id)
      return data.data ?? data
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Erro ao obter URL do PDF.'
      throw e
    }
  }

  function setFilters(newFilters) {
    filters.value = { ...filters.value, ...newFilters }
  }

  function reset() {
    list.value = []
    pagination.value = { next_cursor: null, prev_cursor: null, per_page: 20, total: null }
    current.value = null
    filters.value = {
      status: null,
      type: null,
      patient_id: null,
      professional_id: null,
      expires_after: null,
      expires_before: null,
      cursor: null,
      per_page: 20,
    }
    loading.value = false
    error.value = null
    uploading.value = false
  }

  return {
    // state
    list,
    pagination,
    current,
    filters,
    loading,
    error,
    uploading,
    // getters
    byId,
    byType,
    criticalityCount,
    // actions
    fetchList,
    fetchOne,
    create,
    updateNotes: updateNotesAction,
    cancel,
    uploadPdf: uploadPdfAction,
    requestDownloadUrl,
    setFilters,
    reset,
  }
})

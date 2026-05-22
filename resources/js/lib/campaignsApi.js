/**
 * **T169 (Fase 8 — Lote C US-9.1)** — HTTP client de Campanhas.
 *
 * Reusa axios singleton (Bearer + X-Tenant-Slug interceptor — Fase 4).
 */
import api from '@/lib/api'

export const listCampaigns = (filters = {}) =>
  api.get('/campaigns', { params: filters })

export const getCampaign = (id) =>
  api.get(`/campaigns/${id}`)

export const createCampaign = (payload) =>
  api.post('/campaigns', payload)

export const updateCampaign = (id, payload) =>
  api.patch(`/campaigns/${id}`, payload)

export const previewCampaign = (id) =>
  api.post(`/campaigns/${id}/preview`)

export const dispatchCampaign = (id) =>
  api.post(`/campaigns/${id}/dispatch`, { confirmation: true })

export const cancelCampaign = (id, reason = null) =>
  api.post(`/campaigns/${id}/cancel`, { reason })

export const getCampaignReport = (id) =>
  api.get(`/campaigns/${id}/report`)

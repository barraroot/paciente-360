/**
 * **T033 (Fase 8 — Lote A US-13.1)** — Helpers HTTP para o módulo Privacidade.
 *
 * Reusa instância `api` (Bearer + X-Tenant-Slug interceptor — Fase 4).
 */
import api from '@/lib/api'

// ─── Consents ─────────────────────────────────────────────────────────────────

/**
 * GET /api/v1/privacy/consents
 * @param {Object} filters - patient_id, finalidade, state, channel, page, per_page
 */
export const listConsents = (filters = {}) =>
  api.get('/privacy/consents', { params: filters })

/**
 * GET /api/v1/privacy/consents/{id}
 */
export const getConsent = (id) =>
  api.get(`/privacy/consents/${id}`)

/**
 * POST /api/v1/privacy/consents
 * @param {Object} payload - patient_id, channel, finalidade, state, evidence_message_id?,
 *                           evidence_snapshot?, terms_version?
 */
export const recordConsent = (payload) =>
  api.post('/privacy/consents', payload)

/**
 * POST /api/v1/privacy/consents/revoke
 * @param {Object} payload - patient_id, finalidade, channel, scope?, evidence_message_id?
 */
export const revokeConsent = (payload) =>
  api.post('/privacy/consents/revoke', payload)

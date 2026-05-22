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

// ─── Forgetting Requests (US-13.2) ─────────────────────────────────────────

/**
 * GET /api/v1/privacy/forgetting-requests
 * @param {Object} filters - status, only_open, page, per_page
 */
export const listForgettingRequests = (filters = {}) =>
  api.get('/privacy/forgetting-requests', { params: filters })

/**
 * GET /api/v1/privacy/forgetting-requests/{id}
 */
export const getForgettingRequest = (id) =>
  api.get(`/privacy/forgetting-requests/${id}`)

/**
 * POST /api/v1/privacy/forgetting-requests
 * @param {Object} payload - patient_id, channel_of_request, verification_notes?
 */
export const createForgettingRequest = (payload) =>
  api.post('/privacy/forgetting-requests', payload)

/**
 * POST /api/v1/privacy/forgetting-requests/{id}/execute
 * @param {string|number} id
 * @param {Object} payload - confirmation: boolean, notes?
 */
export const executeForgettingRequest = (id, payload = { confirmation: true }) =>
  api.post(`/privacy/forgetting-requests/${id}/execute`, payload)

/**
 * POST /api/v1/privacy/forgetting-requests/{id}/deny
 * @param {string|number} id
 * @param {Object} payload - reason
 */
export const denyForgettingRequest = (id, payload) =>
  api.post(`/privacy/forgetting-requests/${id}/deny`, payload)

// ─── Portability Requests (US-13.2 — Q28) ──────────────────────────────────

/**
 * GET /api/v1/privacy/portability-requests
 */
export const listPortabilityRequests = (filters = {}) =>
  api.get('/privacy/portability-requests', { params: filters })

/**
 * GET /api/v1/privacy/portability-requests/{id}
 */
export const getPortabilityRequest = (id) =>
  api.get(`/privacy/portability-requests/${id}`)

/**
 * POST /api/v1/privacy/portability-requests
 * @param {Object} payload - patient_id
 */
export const createPortabilityRequest = (payload) =>
  api.post('/privacy/portability-requests', payload)

/**
 * POST /api/v1/privacy/portability-requests/{id}/execute
 */
export const executePortabilityRequest = (id) =>
  api.post(`/privacy/portability-requests/${id}/execute`)

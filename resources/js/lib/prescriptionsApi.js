/**
 * T075 (Fase 7) — Helpers HTTP para o módulo de Gestão de Receituários (US-8.1).
 *
 * Reusa instância `api` (Bearer + X-Tenant-Slug interceptor — Fase 4).
 */
import api from '@/lib/api'

// ─── Listagem / Detalhe ───────────────────────────────────────────────────────

/**
 * GET /api/v1/prescriptions
 * @param {Object} filters - status, type, patient_id, professional_id, expires_after, expires_before, cursor, per_page
 */
export const listPrescriptions = (filters = {}) =>
  api.get('/prescriptions', { params: filters })

/**
 * GET /api/v1/prescriptions/{id}
 */
export const getPrescription = (id) =>
  api.get(`/prescriptions/${id}`)

// ─── Criação ──────────────────────────────────────────────────────────────────

/**
 * POST /api/v1/prescriptions
 * @param {Object} payload - patient_id, professional_id, type, issued_at, duration_days, items[], notes, appointment_id, alert_disabled
 */
export const createPrescription = (payload) =>
  api.post('/prescriptions', payload)

// ─── Atualização de notas ─────────────────────────────────────────────────────

/**
 * PATCH /api/v1/prescriptions/{id}
 * @param {string|number} id
 * @param {string|null} notes
 */
export const updateNotes = (id, notes) =>
  api.patch(`/prescriptions/${id}`, { notes })

// ─── Cancelamento ─────────────────────────────────────────────────────────────

/**
 * POST /api/v1/prescriptions/{id}/cancel
 * @param {string|number} id
 * @param {{ category: string, reason: string }} payload
 */
export const cancelPrescription = (id, { category, reason }) =>
  api.post(`/prescriptions/${id}/cancel`, {
    cancellation_reason_category: category,
    cancellation_reason: reason,
  })

// ─── PDF ──────────────────────────────────────────────────────────────────────

/**
 * POST /api/v1/prescriptions/{id}/pdf (multipart/form-data)
 * @param {string|number} id
 * @param {File} file
 * @param {Function} onProgress - callback(percent: number)
 */
export const uploadPdf = (id, file, onProgress = null) => {
  const formData = new FormData()
  formData.append('pdf', file)

  return api.post(`/prescriptions/${id}/pdf`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: onProgress
      ? (e) => onProgress(Math.round((e.loaded * 100) / (e.total || 1)))
      : undefined,
  })
}

/**
 * GET /api/v1/prescriptions/{id}/pdf
 * Retorna { data: { url, expires_at } }
 */
export const downloadPdf = (id) =>
  api.get(`/prescriptions/${id}/pdf`)

// ─── Autocomplete de medicamentos ─────────────────────────────────────────────

/**
 * GET /api/v1/prescriptions/medications/autocomplete?q={query}
 * Retorna sugestões baseadas no histórico do médico.
 */
export const autocompleteMedications = (q) =>
  api.get('/prescriptions/medications/autocomplete', { params: { q } })

// ─── Configuração de alertas (US-8.2) ────────────────────────────────────────

/**
 * PATCH /api/v1/prescriptions/{id}/alert-config
 * Ativa ou desativa alertas de vencimento (apenas para type=common).
 * @param {string|number} id
 * @param {{ alert_disabled: boolean }} payload
 */
export const updateAlertConfig = (id, payload) =>
  api.patch(`/prescriptions/${id}/alert-config`, payload)

/**
 * GET /api/v1/prescriptions/{id}/alerts
 * Retorna lista de alertas D-15/D-7/D-1 com status e skip_reason.
 * @param {string|number} id
 * @returns {Promise<Array>} lista de alertas
 */
export const getPrescriptionAlerts = async (id) => {
  const { data } = await api.get(`/prescriptions/${id}/alerts`)
  return data.data ?? data
}

// ─── Renovação (US-8.3) ───────────────────────────────────────────────────────

/**
 * POST /api/v1/prescriptions/{id}/renew
 * Acionado pelo profissional ou IA para registrar a intenção de renovação.
 * Retorna 201 com PrescriptionRenewal ou 422 prescription_not_eligible_for_renewal.
 *
 * @param {string|number} id
 * @param {{ initiated_by: 'professional'|'ai'|'patient', appointment_id?: number }} payload
 */
export const renewPrescription = (id, payload) =>
  api.post(`/prescriptions/${id}/renew`, payload)

/**
 * Busca a renovação de receita vinculada a uma consulta.
 *
 * TODO: endpoint GET /api/v1/appointments/{id}/renewal ainda não existe no backend.
 * Quando implementado, substituir o corpo desta função por:
 *   const { data } = await api.get(`/appointments/${appointmentId}/renewal`)
 *   return data.data ?? data
 *
 * @param {number} appointmentId
 * @returns {Promise<Object|null>}
 */
export const getAppointmentRenewal = async (_appointmentId) => {
  // Placeholder — retorna null até endpoint ser implementado
  return null
}

// ─── Relatório (US-8.4) ───────────────────────────────────────────────────────

/**
 * GET /api/v1/prescription-reports
 * Retorna listagem paginada (cursor-based) com criticality + is_renewed.
 * Receitas controladas sem permissão retornam como PrescriptionMasked.
 *
 * @param {Object} filters - status, type, professional_id, patient_id, expires_after, expires_before, cursor, per_page
 */
export const getReport = (filters = {}) =>
  api.get('/prescription-reports', { params: filters })

/**
 * GET /api/v1/prescription-reports/export
 * StreamedResponse CSV. Filename com prefixo CONFIDENCIAL_ quando ≥1 controlada.
 * Baixa o arquivo via blob + URL.createObjectURL.
 *
 * @param {Object} filters - mesmos filtros de getReport
 * @returns {Promise<{ filename: string, hasControlled: boolean }>}
 */
export const exportReportCsv = async (filters = {}) => {
  const response = await api.get('/prescription-reports/export', {
    params: filters,
    responseType: 'blob',
  })

  // Extrai filename do header Content-Disposition
  const cd = response.headers['content-disposition'] || ''
  const match = cd.match(/filename="?([^";]+)"?/)
  const filename =
    match?.[1] ?? `receituarios_${new Date().toISOString().slice(0, 10)}.csv`

  const blob = new Blob([response.data], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)

  return { filename, hasControlled: filename.startsWith('CONFIDENCIAL_') }
}

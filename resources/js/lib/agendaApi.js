/**
 * T049 (Fase 5) — Helpers HTTP para o módulo Agenda.
 *
 * Reusa instância `api` (Bearer + X-Tenant-Slug interceptor — Fase 4).
 */
import api from '@/lib/api'

// ─── Schedule (US-6.1) ────────────────────────────────────────────
export const getSchedule = (professionalId) =>
  api.get(`/agenda/professionals/${professionalId}/schedules`)

export const updateSchedule = (professionalId, payload) =>
  api.put(`/agenda/professionals/${professionalId}/schedules`, payload)

export const listExceptions = (professionalId, { from, to } = {}) =>
  api.get(`/agenda/professionals/${professionalId}/schedule-exceptions`, {
    params: { from, to },
  })

export const createException = (professionalId, payload) =>
  api.post(`/agenda/professionals/${professionalId}/schedule-exceptions`, payload)

export const deleteException = (id) =>
  api.delete(`/agenda/schedule-exceptions/${id}`)

// ─── AppointmentType (US-6.2) ─────────────────────────────────────
export const listAppointmentTypes = ({ includeInactive = false, page = 1 } = {}) =>
  api.get('/agenda/appointment-types', {
    params: { include_inactive: includeInactive ? 1 : 0, page },
  })

export const createAppointmentType = (payload) =>
  api.post('/agenda/appointment-types', payload)

export const getAppointmentType = (id) =>
  api.get(`/agenda/appointment-types/${id}`)

export const updateAppointmentType = (id, payload) =>
  api.patch(`/agenda/appointment-types/${id}`, payload)

export const deleteAppointmentType = (id) =>
  api.delete(`/agenda/appointment-types/${id}`)

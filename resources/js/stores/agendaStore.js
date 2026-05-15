/**
 * T089 — Pinia store da Agenda (US-6.3).
 *
 * Sincronia multi-aba via Reverb canal `tenant.{X}.agenda`.
 */
import { defineStore } from 'pinia'
import { listAppointments, createAppointment, rescheduleAppointment } from '@/lib/agendaApi'

export const useAgendaStore = defineStore('agenda', {
  state: () => ({
    appointments: [],
    loading: false,
    range: { from: null, to: null },
    professionalFilter: null,
  }),

  actions: {
    async fetch(params = {}) {
      this.loading = true
      try {
        const { data } = await listAppointments({
          ...params,
          professional_id: this.professionalFilter,
          from: this.range.from,
          to: this.range.to,
        })
        this.appointments = data.data
      } finally {
        this.loading = false
      }
    },

    async create(payload) {
      const { data } = await createAppointment(payload)
      this.appointments.push(data.data)
      return data.data
    },

    async reschedule(id, payload) {
      const { data } = await rescheduleAppointment(id, payload)
      const idx = this.appointments.findIndex((a) => a.id === id)
      if (idx >= 0) this.appointments[idx] = data.data
      return data.data
    },

    /**
     * Subscribe to Reverb broadcast channel for sync multi-aba.
     * Chamado em onMounted da AgendaPage.
     */
    subscribeBroadcast(tenantId, echo) {
      if (!echo) return null
      const channel = echo.private(`tenant.${tenantId}.agenda`)

      channel.listen('.consulta.criada', (payload) => {
        // Refresh leve do range visível
        this.fetch()
      })
      channel.listen('.consulta.reagendada', (payload) => {
        this.fetch()
      })

      return channel
    },
  },
})

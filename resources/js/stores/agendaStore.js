/**
 * Pinia store da Agenda (US-6.3).
 *
 * Sincronia multi-aba via Reverb canal `tenant.{X}.agenda`.
 * Debounce de 800ms nos eventos broadcast para evitar refetch em rajada.
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
                this.appointments = data.data ?? []
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
            if (idx >= 0) {
                this.appointments[idx] = data.data
            }
            return data.data
        },

        /**
         * Subscribe ao canal Reverb para sync multi-aba.
         * Refetch com debounce de 800ms para agrupar eventos em rajada.
         * Chamado em onMounted da AgendaPage.
         *
         * @param {string|number} tenantId
         * @param {object} echo — instância window.Echo
         * @returns {object} canal Echo
         */
        subscribeBroadcast(tenantId, echo) {
            if (!echo || !tenantId) return null

            let debounceTimer = null

            const debouncedFetch = () => {
                if (debounceTimer) clearTimeout(debounceTimer)
                debounceTimer = setTimeout(() => {
                    this.fetch().catch(() => {})
                }, 800)
            }

            const channel = echo.private(`tenant.${tenantId}.agenda`)

            channel.listen('.consulta.criada', debouncedFetch)
            channel.listen('.consulta.reagendada', debouncedFetch)
            channel.listen('.consulta.cancelada', debouncedFetch)

            return channel
        },
    },
})

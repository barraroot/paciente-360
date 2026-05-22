import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * T204 (Fase 8 — Lote D US-11.1) — Store Pinia de webhooks.
 */
export const useWebhooksStore = defineStore('webhooks', {
    state: () => ({
        endpoints: [],
        deliveries: [],
        deadLetter: [],
        loading: false,
        error: null,
        lastSecretPlaintext: null, // Exibido UMA vez após criação.
    }),

    actions: {
        async loadEndpoints() {
            this.loading = true
            this.error = null
            try {
                const { data } = await axios.get('/api/v1/integrations/webhooks')
                this.endpoints = data.data ?? data
            } catch (e) {
                this.error = e?.response?.data?.message ?? 'Falha ao carregar endpoints.'
            } finally {
                this.loading = false
            }
        },

        async createEndpoint(payload) {
            const { data } = await axios.post('/api/v1/integrations/webhooks', payload)
            this.lastSecretPlaintext = data.meta?.secret_plaintext ?? null
            await this.loadEndpoints()
            return data.data
        },

        async updateEndpoint(id, payload) {
            const { data } = await axios.patch(`/api/v1/integrations/webhooks/${id}`, payload)
            await this.loadEndpoints()
            return data.data
        },

        async pauseResume(id) {
            await axios.post(`/api/v1/integrations/webhooks/${id}/pause`)
            await this.loadEndpoints()
        },

        async deleteEndpoint(id) {
            await axios.delete(`/api/v1/integrations/webhooks/${id}`)
            await this.loadEndpoints()
        },

        async loadDeliveries(webhookId) {
            const { data } = await axios.get(`/api/v1/integrations/webhooks/${webhookId}/deliveries`)
            this.deliveries = data.data ?? data
        },

        async loadDeadLetter() {
            const { data } = await axios.get('/api/v1/integrations/webhooks/dlq')
            this.deadLetter = data.data ?? data
        },

        async resendDlq(dlqId) {
            await axios.post(`/api/v1/integrations/webhooks/dlq/${dlqId}/resend`)
            await this.loadDeadLetter()
        },

        clearSecret() {
            this.lastSecretPlaintext = null
        },
    },
})

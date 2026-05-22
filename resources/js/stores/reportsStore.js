import { defineStore } from 'pinia'
import { reportsApi } from '@/lib/reportsApi'

/**
 * T258 (Fase 8 — Lote E) — Store Pinia de relatórios.
 *
 * Cache leve por período → reaproveita drill-downs sem refetch.
 */
export const useReportsStore = defineStore('reports', {
    state: () => ({
        executive: { data: null, loading: false, error: null, lastFetched: null, period: null },
        drillDown: { data: null, loading: false, error: null, metric: null },
        operational: { data: null, loading: false, error: null },
        clinical: { data: null, loading: false, error: null },
        pdfExport: { loading: false, error: null },
    }),

    actions: {
        async loadExecutive(params = { preset: '30d' }) {
            this.executive.loading = true
            this.executive.error = null
            try {
                const data = await reportsApi.fetchExecutiveDashboard(params)
                this.executive.data = data.data ?? data
                this.executive.lastFetched = new Date()
                this.executive.period = params
            } catch (e) {
                this.executive.error = e?.response?.data?.message ?? 'Falha ao carregar dashboard.'
            } finally {
                this.executive.loading = false
            }
        },

        async loadDrillDown(metric, params = {}) {
            this.drillDown.loading = true
            this.drillDown.error = null
            this.drillDown.metric = metric
            try {
                const data = await reportsApi.fetchDrillDown(metric, params)
                this.drillDown.data = data.data ?? data
            } catch (e) {
                this.drillDown.error = e?.response?.data?.message ?? 'Falha ao carregar drill-down.'
            } finally {
                this.drillDown.loading = false
            }
        },

        async exportPdf(params = {}) {
            this.pdfExport.loading = true
            this.pdfExport.error = null
            try {
                const response = await reportsApi.exportDashboardPdf(params)
                const blob = new Blob([response.data], { type: 'application/pdf' })
                const url = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = url
                link.download = `dashboard-${new Date().toISOString().slice(0, 10)}.pdf`
                link.click()
                URL.revokeObjectURL(url)
                return true
            } catch (e) {
                this.pdfExport.error = e?.response?.data?.message ?? 'Falha ao exportar PDF.'
                return false
            } finally {
                this.pdfExport.loading = false
            }
        },

        async loadOperational(params = {}) {
            this.operational.loading = true
            this.operational.error = null
            try {
                const data = await reportsApi.fetchOperationalReport(params)
                this.operational.data = data.data ?? data
            } catch (e) {
                this.operational.error = e?.response?.data?.message ?? 'Falha ao carregar relatório operacional.'
            } finally {
                this.operational.loading = false
            }
        },

        async loadClinical(params = {}) {
            this.clinical.loading = true
            this.clinical.error = null
            try {
                const data = await reportsApi.fetchClinicalReport(params)
                this.clinical.data = data.data ?? data
            } catch (e) {
                this.clinical.error = e?.response?.data?.message ?? 'Falha ao carregar relatório clínico.'
            } finally {
                this.clinical.loading = false
            }
        },
    },
})

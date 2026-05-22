import axios from 'axios'

/**
 * T258 (Fase 8 — Lote E) — Cliente API de relatórios.
 *
 * Bearer + X-Tenant-Slug aplicados via interceptors globais (Fase 4).
 */
export const reportsApi = {
    fetchExecutiveDashboard(params = {}) {
        return axios.get('/api/v1/reports/executive', { params }).then((r) => r.data)
    },

    fetchDrillDown(metric, params = {}) {
        return axios.get(`/api/v1/reports/executive/drill/${metric}`, { params }).then((r) => r.data)
    },

    exportDashboardPdf(params = {}) {
        return axios.post('/api/v1/reports/executive/export-pdf', params, {
            responseType: 'blob',
        })
    },

    fetchOperationalReport(params = {}) {
        return axios.get('/api/v1/reports/operational', { params }).then((r) => r.data)
    },

    fetchClinicalReport(params = {}) {
        return axios.get('/api/v1/reports/clinical', { params }).then((r) => r.data)
    },
}

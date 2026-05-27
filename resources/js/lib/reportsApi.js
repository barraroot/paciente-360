import api from '@/lib/api.js';

/**
 * T258 (Fase 8 — Lote E) — Cliente API de relatórios.
 *
 * Usa a instância `@/lib/api` (baseURL `/api/v1`) — interceptors injetam
 * Bearer + X-Tenant-Slug (Fase 4). UX-010: antes usava `axios` cru → 401.
 */
export const reportsApi = {
    fetchExecutiveDashboard(params = {}) {
        return api.get('/reports/executive', { params }).then((r) => r.data);
    },

    fetchDrillDown(metric, params = {}) {
        return api.get(`/reports/executive/drill/${metric}`, { params }).then((r) => r.data);
    },

    exportDashboardPdf(params = {}) {
        return api.post('/reports/executive/export-pdf', params, {
            responseType: 'blob',
        });
    },

    fetchOperationalReport(params = {}) {
        return api.get('/reports/operational', { params }).then((r) => r.data);
    },

    fetchClinicalReport(params = {}) {
        return api.get('/reports/clinical', { params }).then((r) => r.data);
    },
};

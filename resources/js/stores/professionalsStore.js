import { defineStore } from 'pinia';
import api from '@/lib/api.js';

/**
 * Pinia store — Gestão de Profissionais (Spec 012).
 */
export const useProfessionalsStore = defineStore('professionals', {
    state: () => ({
        list: [],
        filters: { is_active: 'true', search: '' },
        loading: false,
        error: null,
        forbidden: false,
        especialidadesSuggestions: [],
    }),

    actions: {
        async fetchList() {
            this.loading = true;
            this.error = null;
            this.forbidden = false;
            try {
                const { data } = await api.get('/professionals', { params: this.filters });
                this.list = data.data ?? data;
            } catch (e) {
                // 403: distingue "sem permissão" de lista vazia, para a página
                // renderizar acesso negado em vez do empty state enganoso.
                if (e?.response?.status === 403) {
                    this.forbidden = true;
                    this.list = [];
                }
                this.error = e?.response?.data?.message ?? 'Falha ao carregar profissionais.';
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            const { data } = await api.post('/professionals', payload);
            await this.fetchList();
            return data.data ?? data;
        },

        async update(id, payload) {
            const { data } = await api.put(`/professionals/${id}`, payload);
            await this.fetchList();
            return data.data ?? data;
        },

        async deactivate(id) {
            await api.delete(`/professionals/${id}`);
            await this.fetchList();
        },

        async activate(id) {
            await api.post(`/professionals/${id}/activate`);
            await this.fetchList();
        },

        async checkEmail(email) {
            const { data } = await api.post('/professionals/check-email', { email });
            return data;
        },

        async fetchEspecialidades(q = '') {
            const { data } = await api.get('/professionals/especialidades', { params: { q } });
            this.especialidadesSuggestions = data.data ?? [];
            return this.especialidadesSuggestions;
        },

        setFilter(key, value) {
            this.filters[key] = value;
            this.fetchList();
        },
    },
});

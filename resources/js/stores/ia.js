import { defineStore } from 'pinia';
import api from '@/lib/api.js';

/**
 * Store Pinia da IA Matricial (Fase 15).
 *
 * US1 — catálogo de modelos + CRUD de personas.
 * Endpoints:
 *   GET    /ai/models
 *   GET    /ai/personas
 *   POST   /ai/personas
 *   GET    /ai/personas/{id}
 *   PUT    /ai/personas/{id}
 *   DELETE /ai/personas/{id}
 *   POST   /ai/personas/{id}/activate | /deactivate
 */
export const useIaStore = defineStore('ia', {
    state: () => ({
        models: [],
        personas: [],
        selectedPersona: null,
        matrix: [],
        loading: false,
        saving: false,
        error: null,
    }),

    getters: {
        activeModels: (state) => state.models.filter((m) => m.is_active),
        personaById: (state) => (id) => state.personas.find((p) => p.id === Number(id)) ?? null,
    },

    actions: {
        async fetchModels() {
            const { data } = await api.get('/ai/models');
            this.models = data.data ?? [];
            return this.models;
        },

        async fetchPersonas(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get('/ai/personas', { params });
                this.personas = data.data ?? [];
                return this.personas;
            } catch (e) {
                this.error = e?.response?.data?.message ?? 'Erro ao carregar personas.';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async fetchPersona(id) {
            const { data } = await api.get(`/ai/personas/${id}`);
            this.selectedPersona = data.data;
            return this.selectedPersona;
        },

        async createPersona(payload) {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await api.post('/ai/personas', payload);
                this.personas.unshift(data.data);
                return data.data;
            } finally {
                this.saving = false;
            }
        },

        async updatePersona(id, payload) {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await api.put(`/ai/personas/${id}`, payload);
                this._replacePersona(data.data);
                return data.data;
            } finally {
                this.saving = false;
            }
        },

        async deletePersona(id) {
            await api.delete(`/ai/personas/${id}`);
            this.personas = this.personas.filter((p) => p.id !== id);
        },

        async setPersonaActive(id, active) {
            const action = active ? 'activate' : 'deactivate';
            const { data } = await api.post(`/ai/personas/${id}/${action}`);
            this._replacePersona(data.data);
            return data.data;
        },

        async fetchMatrix() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get('/ai/persona-channels');
                this.matrix = data.data ?? [];
                return this.matrix;
            } finally {
                this.loading = false;
            }
        },

        async saveMatrix(cells) {
            this.saving = true;
            try {
                const { data } = await api.put('/ai/persona-channels', { cells });
                this.matrix = data.data ?? [];
                return this.matrix;
            } finally {
                this.saving = false;
            }
        },

        _replacePersona(persona) {
            const idx = this.personas.findIndex((p) => p.id === persona.id);
            if (idx !== -1) {
                this.personas.splice(idx, 1, persona);
            }
            if (this.selectedPersona?.id === persona.id) {
                this.selectedPersona = persona;
            }
        },
    },
});

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
        knowledgeBases: [],
        selectedBase: null,
        guardrails: [],
        selectedGuardrail: null,
        executionLogs: [],
        executionLogsMeta: null,
        workContext: null,
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

        // Feature 017 (US2) — Contexto de Trabalho da clínica (singleton).
        async fetchWorkContext() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get('/ai/work-context');
                this.workContext = data.data ?? null;
                return this.workContext;
            } catch (e) {
                this.error =
                    e?.response?.data?.message ?? 'Erro ao carregar o contexto de trabalho.';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async saveWorkContext(payload) {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await api.put('/ai/work-context', payload);
                this.workContext = data.data ?? null;
                return this.workContext;
            } finally {
                this.saving = false;
            }
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

        // ─── US4 — Bases de conhecimento (RAG) ──────────────────────────
        async fetchKnowledgeBases(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get('/ai/knowledge-bases', { params });
                this.knowledgeBases = data.data ?? [];
                return this.knowledgeBases;
            } catch (e) {
                this.error =
                    e?.response?.data?.message ?? 'Erro ao carregar bases de conhecimento.';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async fetchKnowledgeBase(id) {
            const { data } = await api.get(`/ai/knowledge-bases/${id}`);
            this.selectedBase = data.data;
            return this.selectedBase;
        },

        async createKnowledgeBase(payload) {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await api.post('/ai/knowledge-bases', payload);
                this.knowledgeBases.unshift(data.data);
                return data.data;
            } finally {
                this.saving = false;
            }
        },

        async updateKnowledgeBase(id, payload) {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await api.put(`/ai/knowledge-bases/${id}`, payload);
                this._replaceBase(data.data);
                return data.data;
            } finally {
                this.saving = false;
            }
        },

        async deleteKnowledgeBase(id) {
            await api.delete(`/ai/knowledge-bases/${id}`);
            this.knowledgeBases = this.knowledgeBases.filter((b) => b.id !== id);
        },

        async setKnowledgeBaseActive(id, active) {
            const action = active ? 'activate' : 'deactivate';
            const { data } = await api.post(`/ai/knowledge-bases/${id}/${action}`);
            this._replaceBase(data.data);
            return data.data;
        },

        async syncPersonaKnowledgeBases(personaId, knowledgeBaseIds) {
            const { data } = await api.put(`/ai/personas/${personaId}/knowledge-bases`, {
                knowledge_base_ids: knowledgeBaseIds,
            });
            return data.data ?? [];
        },

        _replaceBase(base) {
            const idx = this.knowledgeBases.findIndex((b) => b.id === base.id);
            if (idx !== -1) {
                this.knowledgeBases.splice(idx, 1, base);
            }
            if (this.selectedBase?.id === base.id) {
                this.selectedBase = base;
            }
        },

        // ─── US5 — Guardrails da clínica ────────────────────────────────
        async fetchGuardrails(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get('/ai/guardrails', { params });
                this.guardrails = data.data ?? [];
                return this.guardrails;
            } catch (e) {
                this.error = e?.response?.data?.message ?? 'Erro ao carregar guardrails.';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async fetchGuardrail(id) {
            const { data } = await api.get(`/ai/guardrails/${id}`);
            this.selectedGuardrail = data.data;
            return this.selectedGuardrail;
        },

        async createGuardrail(payload) {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await api.post('/ai/guardrails', payload);
                this.guardrails.unshift(data.data);
                return data.data;
            } finally {
                this.saving = false;
            }
        },

        async updateGuardrail(id, payload) {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await api.put(`/ai/guardrails/${id}`, payload);
                this._replaceGuardrail(data.data);
                return data.data;
            } finally {
                this.saving = false;
            }
        },

        async deleteGuardrail(id) {
            await api.delete(`/ai/guardrails/${id}`);
            this.guardrails = this.guardrails.filter((g) => g.id !== id);
        },

        async setGuardrailActive(id, active) {
            const action = active ? 'activate' : 'deactivate';
            const { data } = await api.post(`/ai/guardrails/${id}/${action}`);
            this._replaceGuardrail(data.data);
            return data.data;
        },

        async syncPersonaGuardrails(personaId, guardrailIds) {
            const { data } = await api.put(`/ai/personas/${personaId}/guardrails`, {
                guardrail_ids: guardrailIds,
            });
            return data.data ?? [];
        },

        // ─── US6 — Controle da IA na conversa ───────────────────────────
        async fetchConversationAiState(conversationId) {
            const { data } = await api.get(`/ai/conversations/${conversationId}/state`);
            return data.data;
        },

        async pauseConversationAi(conversationId) {
            const { data } = await api.post(`/ai/conversations/${conversationId}/pause`);
            return data.data;
        },

        async resumeConversationAi(conversationId) {
            const { data } = await api.post(`/ai/conversations/${conversationId}/resume`);
            return data.data;
        },

        // ─── US7 — Logs de execução ─────────────────────────────────────
        async fetchExecutionLogs(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get('/ai/execution-logs', { params });
                this.executionLogs = data.data ?? [];
                this.executionLogsMeta = data.meta ?? null;
                return this.executionLogs;
            } catch (e) {
                this.error = e?.response?.data?.message ?? 'Erro ao carregar logs.';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        _replaceGuardrail(guardrail) {
            const idx = this.guardrails.findIndex((g) => g.id === guardrail.id);
            if (idx !== -1) {
                this.guardrails.splice(idx, 1, guardrail);
            }
            if (this.selectedGuardrail?.id === guardrail.id) {
                this.selectedGuardrail = guardrail;
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

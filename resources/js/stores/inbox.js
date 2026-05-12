import { defineStore } from 'pinia';
import api from '@/lib/api.js';

/**
 * Store Pinia para a Inbox unificada (US-4.4).
 *
 * Endpoints consumidos:
 *   GET    /inbox/conversations             (lista + filtros + aggregations)
 *   GET    /inbox/conversations/{id}        (detalhe)
 *   GET    /inbox/conversations/{id}/messages?cursor=
 *   POST   /inbox/conversations/{id}/messages
 *   POST   /inbox/conversations/{id}/read
 *   POST   /inbox/conversations/{id}/resolve
 *   POST   /inbox/conversations/{id}/reopen
 *   POST   /inbox/conversations/{id}/link-patient
 *   GET    /inbox/poll?since=              (long-polling fallback NC-11.c)
 */
export const useInboxStore = defineStore('inbox', {
    state: () => ({
        /** @type {Array<Object>} lista de conversas carregadas */
        conversations: [],

        /** @type {Array<Object>} usuários atribuíveis ao tenant (cache) */
        assignableUsers: [],

        /** @type {boolean} */
        loadingAssignableUsers: false,

        /** @type {Record<string|number, Array<Object>>} histórico de atribuições indexado por id de conversa */
        assignmentsByConversationId: {},

        /** @type {boolean} */
        loadingAssignments: false,

        /** @type {Array<Object>} regras de atribuição automática */
        assignmentRules: [],

        /** @type {boolean} */
        loadingAssignmentRules: false,

        /** @type {boolean} */
        savingAssignmentRules: false,

        /** @type {Array<Object>} respostas rápidas visíveis para o usuário (tenant + privadas) */
        quickReplies: [],

        /** @type {boolean} */
        loadingQuickReplies: false,

        /** @type {Record<string|number, Array<Object>>} mensagens indexadas por conversa id */
        messagesByConversationId: {},

        /** @type {string|number|null} id da conversa selecionada */
        selectedConversationId: null,

        /**
         * Cursor para scroll-back infinito de mensagens.
         * @type {Record<string|number, string|null>}
         */
        cursorByConversationId: {},

        /** @type {boolean} indica se há uma página anterior de mensagens */
        hasMoreMessagesByConversationId: {},

        /**
         * Filtros ativos.
         * @type {{
         *   status: string[],
         *   channel_type: string[],
         *   channel_id: string|null,
         *   assigned_user_id: string|null,
         *   patient_id: string|null,
         *   has_media: boolean|null,
         *   age: string|null,
         *   q: string,
         *   last_activity_from: string|null,
         *   last_activity_to: string|null,
         *   ai_paused: boolean|null,
         * }}
         */
        filters: {
            status: [],
            channel_type: [],
            channel_id: null,
            assigned_user_id: null,
            patient_id: null,
            has_media: null,
            age: null,
            q: '',
            last_activity_from: null,
            last_activity_to: null,
            ai_paused: null,
        },

        /** @type {{ page: number, per_page: number, total: number, last_page: number }} */
        pagination: {
            page: 1,
            per_page: 30,
            total: 0,
            last_page: 1,
        },

        /**
         * Aggregations para badges de header/filtros.
         * @type {{ by_status: Record<string, number>, unassigned: number, mine: number }}
         */
        aggregations: {
            by_status: {},
            unassigned: 0,
            mine: 0,
        },

        loading: false,
        loadingMessages: false,
        sendingMessage: false,

        /** @type {string|null} */
        error: null,

        /** @type {string|null} */
        messagesError: null,

        /** @type {boolean} true quando Reverb está conectado */
        reverbConnected: false,

        /** @type {boolean} true quando long-polling fallback ativo */
        pollingMode: false,
    }),

    getters: {
        /**
         * Conversa atualmente selecionada.
         * @param {Object} state
         * @returns {Object|null}
         */
        selectedConversation: (state) => {
            if (!state.selectedConversationId) { return null; }
            return (
                state.conversations.find(
                    (c) => String(c.id) === String(state.selectedConversationId),
                ) ?? null
            );
        },

        /**
         * Mensagens da conversa selecionada.
         * @param {Object} state
         * @returns {Array<Object>}
         */
        selectedMessages: (state) => {
            if (!state.selectedConversationId) { return []; }
            return state.messagesByConversationId[state.selectedConversationId] ?? [];
        },

        /**
         * Número de filtros ativos (excluindo page/per_page).
         * @param {Object} state
         * @returns {number}
         */
        filtersActive: (state) => {
            let count = 0;
            if (state.filters.status.length > 0) { count++; }
            if (state.filters.channel_type.length > 0) { count++; }
            if (state.filters.channel_id) { count++; }
            if (state.filters.assigned_user_id) { count++; }
            if (state.filters.patient_id) { count++; }
            if (state.filters.has_media !== null) { count++; }
            if (state.filters.age) { count++; }
            if (state.filters.q && state.filters.q.length >= 2) { count++; }
            return count;
        },

        /**
         * Total de conversas não lidas em memória.
         * @param {Object} state
         * @returns {number}
         */
        unreadCount: (state) => {
            return state.conversations.reduce(
                (sum, c) => sum + (c.unread_count ?? 0),
                0,
            );
        },
    },

    actions: {
        /**
         * Carrega lista de conversas com filtros e paginação.
         * @param {{ page?: number, replace?: boolean }} opts
         */
        async loadConversations(opts = {}) {
            this.loading = true;
            this.error = null;
            try {
                const params = {
                    ...this.filters,
                    page: opts.page ?? this.pagination.page,
                    per_page: this.pagination.per_page,
                };

                // Arrays: remover chaves vazias para não poluir a query string
                if (!params.status?.length) { delete params.status; }
                if (!params.channel_type?.length) { delete params.channel_type; }
                if (!params.q) { delete params.q; }
                if (params.has_media === null) { delete params.has_media; }
                if (!params.age) { delete params.age; }
                if (!params.assigned_user_id) { delete params.assigned_user_id; }
                if (!params.patient_id) { delete params.patient_id; }
                if (!params.channel_id) { delete params.channel_id; }
                if (!params.last_activity_from) { delete params.last_activity_from; }
                if (!params.last_activity_to) { delete params.last_activity_to; }
                if (params.ai_paused === null) { delete params.ai_paused; }

                const { data } = await api.get('/inbox/conversations', { params });

                const list = data.data ?? [];

                if (opts.replace === false) {
                    // Append (próxima página)
                    this.conversations = [...this.conversations, ...list];
                } else {
                    this.conversations = list;
                }

                if (data.meta) {
                    this.pagination = {
                        page: data.meta.current_page ?? 1,
                        per_page: data.meta.per_page ?? 30,
                        total: data.meta.total ?? 0,
                        last_page: data.meta.last_page ?? 1,
                    };

                    if (data.meta.aggregations) {
                        this.aggregations = data.meta.aggregations;
                    }
                }
            } catch (err) {
                this.error = err.response?.data?.message ?? 'Falha ao carregar conversas.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Carrega detalhes de uma única conversa e atualiza na lista.
         * @param {string|number} id
         * @returns {Promise<Object>}
         */
        async loadConversation(id) {
            try {
                const { data } = await api.get(`/inbox/conversations/${id}`);
                const conv = data.data ?? data;

                const idx = this.conversations.findIndex(
                    (c) => String(c.id) === String(id),
                );
                if (idx !== -1) {
                    this.conversations[idx] = conv;
                } else {
                    this.conversations.unshift(conv);
                }

                return conv;
            } catch (err) {
                this.error = err.response?.data?.message ?? 'Falha ao carregar conversa.';
                throw err;
            }
        },

        /**
         * Carrega mensagens de uma conversa (cursor-paginated para scroll-back).
         * @param {string|number} conversationId
         * @param {string|null} cursor — null carrega as mais recentes
         */
        async loadMessages(conversationId, cursor = null) {
            this.loadingMessages = true;
            this.messagesError = null;
            try {
                const params = {};
                if (cursor) { params.cursor = cursor; }

                const { data } = await api.get(
                    `/inbox/conversations/${conversationId}/messages`,
                    { params },
                );

                const messages = data.data ?? [];
                const nextCursor = data.meta?.next_cursor ?? null;
                const hasMore = data.meta?.has_more ?? false;

                const key = String(conversationId);

                if (!cursor) {
                    // Primeira carga: substitui
                    this.messagesByConversationId[key] = messages;
                } else {
                    // Scroll-back: prepend
                    const existing = this.messagesByConversationId[key] ?? [];
                    this.messagesByConversationId[key] = [...messages, ...existing];
                }

                this.cursorByConversationId[key] = nextCursor;
                this.hasMoreMessagesByConversationId[key] = hasMore;
            } catch (err) {
                this.messagesError =
                    err.response?.data?.message ?? 'Falha ao carregar mensagens.';
                throw err;
            } finally {
                this.loadingMessages = false;
            }
        },

        /**
         * Envia mensagem para uma conversa.
         * @param {string|number} conversationId
         * @param {{ body: string, type?: string, template_id?: string|null }} payload
         * @param {string} idempotencyKey — UUID gerado no componente
         * @returns {Promise<Object>} mensagem criada
         */
        async sendMessage(conversationId, payload, idempotencyKey) {
            this.sendingMessage = true;
            try {
                const { data } = await api.post(
                    `/inbox/conversations/${conversationId}/messages`,
                    payload,
                    { headers: { 'Idempotency-Key': idempotencyKey } },
                );

                const message = data.data ?? data;
                this._appendMessage(String(conversationId), message);

                return message;
            } finally {
                this.sendingMessage = false;
            }
        },

        /**
         * Marca conversa como lida.
         * @param {string|number} conversationId
         */
        async markAsRead(conversationId) {
            try {
                await api.post(`/inbox/conversations/${conversationId}/read`);
                const conv = this.conversations.find(
                    (c) => String(c.id) === String(conversationId),
                );
                if (conv) { conv.unread_count = 0; }
            } catch {
                // Silencioso — não crítico
            }
        },

        /**
         * Resolve uma conversa (status → 'resolvida').
         * @param {string|number} conversationId
         * @param {string|null} note — nota interna opcional
         */
        async resolve(conversationId, note = null) {
            const { data } = await api.post(
                `/inbox/conversations/${conversationId}/resolve`,
                note ? { note } : {},
            );
            const updated = data.data ?? data;
            this._updateConversationInList(updated);
            return updated;
        },

        /**
         * Reabre uma conversa resolvida.
         * @param {string|number} conversationId
         */
        async reopen(conversationId) {
            const { data } = await api.post(
                `/inbox/conversations/${conversationId}/reopen`,
            );
            const updated = data.data ?? data;
            this._updateConversationInList(updated);
            return updated;
        },

        /**
         * Vincula manualmente uma conversa a um paciente.
         * @param {string|number} conversationId
         * @param {string|number} patientId
         */
        async linkPatient(conversationId, patientId) {
            const { data } = await api.post(
                `/inbox/conversations/${conversationId}/link-patient`,
                { patient_id: patientId },
            );
            const updated = data.data ?? data;
            this._updateConversationInList(updated);
            return updated;
        },

        /**
         * US-4.6 — Pausa a IA na conversa (Modo Humano Assume).
         * @param {string|number} conversationId
         * @param {Object} payload — { duration_hours?: number, until?: string, reason?: string }
         */
        async takeoverAi(conversationId, payload = {}) {
            const { data } = await api.post(
                `/inbox/conversations/${conversationId}/takeover`,
                payload,
            );
            const updated = data.data ?? data;
            this._updateConversationInList(updated);
            return updated;
        },

        /**
         * US-4.6 — Libera a IA imediatamente na conversa.
         * @param {string|number} conversationId
         */
        async releaseToAi(conversationId) {
            const { data } = await api.post(
                `/inbox/conversations/${conversationId}/release-to-ai`,
            );
            const updated = data.data ?? data;
            this._updateConversationInList(updated);
            return updated;
        },

        /**
         * Aplica uma mensagem recebida via Reverb broadcast.
         * Atualiza o state local sem nova chamada HTTP.
         * @param {Object} message — MessageResource
         */
        applyIncomingMessage(message) {
            const convId = String(message.conversation_id);
            this._appendMessage(convId, message);

            // Bump conversa na lista (mover para topo + atualizar last_message_at + unread)
            const convIdx = this.conversations.findIndex(
                (c) => String(c.id) === convId,
            );
            if (convIdx !== -1) {
                const conv = { ...this.conversations[convIdx] };
                conv.last_message_at = message.created_at;
                conv.last_message_preview = message.body
                    ? message.body.slice(0, 80)
                    : null;
                // Só incrementa unread se não for a conversa selecionada
                if (String(this.selectedConversationId) !== convId) {
                    conv.unread_count = (conv.unread_count ?? 0) + 1;
                }
                // Remove e re-insere no topo
                this.conversations.splice(convIdx, 1);
                this.conversations.unshift(conv);
            }
        },

        /**
         * Aplica atualização de conversa recebida via Reverb broadcast.
         * @param {Object} conversationUpdate — payload parcial ou ConversationResource completo
         */
        applyConversationUpdate(conversationUpdate) {
            if (!conversationUpdate?.id) { return; }
            const idx = this.conversations.findIndex(
                (c) => String(c.id) === String(conversationUpdate.id),
            );
            if (idx !== -1) {
                this.conversations[idx] = {
                    ...this.conversations[idx],
                    ...conversationUpdate,
                };
            } else {
                // Conversa nova — inserir no topo
                this.conversations.unshift(conversationUpdate);
            }
        },

        /**
         * Long-polling fallback: busca atualizações desde um cursor timestamp.
         * @param {string} since — ISO timestamp do último evento recebido
         */
        async pollUpdates(since) {
            try {
                const { data } = await api.get('/inbox/poll', { params: { since } });
                const events = data.data ?? [];
                for (const event of events) {
                    if (event.type === 'message') {
                        this.applyIncomingMessage(event.payload);
                    } else if (event.type === 'conversation') {
                        this.applyConversationUpdate(event.payload);
                    }
                }
                return data.meta?.last_cursor ?? since;
            } catch {
                // Silencioso no fallback
                return since;
            }
        },

        /**
         * Define o id da conversa selecionada e marca como lida.
         * @param {string|number|null} id
         */
        selectConversation(id) {
            this.selectedConversationId = id;
            if (id) { this.markAsRead(id); }
        },

        /**
         * Reseta filtros para o estado inicial.
         */
        resetFilters() {
            this.filters = {
                status: [],
                channel_type: [],
                channel_id: null,
                assigned_user_id: null,
                patient_id: null,
                has_media: null,
                age: null,
                q: '',
                last_activity_from: null,
                last_activity_to: null,
                ai_paused: null,
            };
        },

        // ─── US-4.5 — Atribuição e Transferência ─────────────────────────────────

        /**
         * Atribui manualmente uma conversa a um usuário ou via auto-assign.
         * @param {string|number} conversationId
         * @param {{ user_id?: number, auto?: boolean }} payload
         * @returns {Promise<Object>} ConversationResource atualizado
         */
        async assign(conversationId, payload) {
            const { data } = await api.post(
                `/inbox/conversations/${conversationId}/assign`,
                payload,
            );
            const updated = data.data ?? data;
            this._updateConversationInList(updated);
            return updated;
        },

        /**
         * Transfere uma conversa para outro usuário ou perfil.
         * @param {string|number} conversationId
         * @param {{ user_id?: number, role?: string, transfer_note: string }} payload
         * @returns {Promise<Object>}
         */
        async transfer(conversationId, payload) {
            const { data } = await api.post(
                `/inbox/conversations/${conversationId}/transfer`,
                payload,
            );
            const updated = data.data ?? data;
            this._updateConversationInList(updated);
            return updated;
        },

        /**
         * Carrega o histórico de atribuições de uma conversa.
         * @param {string|number} conversationId
         * @returns {Promise<Array<Object>>}
         */
        async loadAssignments(conversationId) {
            this.loadingAssignments = true;
            try {
                const { data } = await api.get(
                    `/inbox/conversations/${conversationId}/assignments`,
                );
                const list = data.data ?? data;
                this.assignmentsByConversationId[String(conversationId)] = list;
                return list;
            } finally {
                this.loadingAssignments = false;
            }
        },

        /**
         * Carrega usuários do tenant com permissão inbox.view (exceto financeiro).
         * Cacheia no state para evitar chamadas duplicadas por sessão.
         * @param {{ force?: boolean }} opts
         * @returns {Promise<Array<Object>>}
         */
        async loadAssignableUsers(opts = {}) {
            if (this.assignableUsers.length > 0 && !opts.force) {
                return this.assignableUsers;
            }
            this.loadingAssignableUsers = true;
            try {
                const { data } = await api.get('/users', {
                    params: { per_page: 200, status: 'active' },
                });
                const all = data.data ?? [];
                // Exclui financeiro pois não tem inbox.view
                this.assignableUsers = all.filter(
                    (u) => !(u.roles ?? []).includes('financeiro'),
                );
                return this.assignableUsers;
            } finally {
                this.loadingAssignableUsers = false;
            }
        },

        /**
         * Carrega as regras de atribuição automática do tenant.
         * @returns {Promise<Array<Object>>}
         */
        async loadAssignmentRules() {
            this.loadingAssignmentRules = true;
            try {
                const { data } = await api.get('/inbox/assignment-rules');
                this.assignmentRules = data.data ?? data;
                return this.assignmentRules;
            } finally {
                this.loadingAssignmentRules = false;
            }
        },

        /**
         * Salva o conjunto completo de regras (PUT replace).
         * @param {Array<Object>} rules
         * @returns {Promise<Array<Object>>}
         */
        async updateAssignmentRules(rules) {
            this.savingAssignmentRules = true;
            try {
                const { data } = await api.put('/inbox/assignment-rules', { rules });
                this.assignmentRules = data.data ?? data;
                return this.assignmentRules;
            } finally {
                this.savingAssignmentRules = false;
            }
        },

        // ─── Quick Replies (US-4.7) ───────────────────────────────────────────────

        /**
         * Carrega respostas rápidas visíveis: tenant scope + privadas do usuário.
         * @param {{ scope?: string, q?: string, sort?: string }} options
         * @returns {Promise<Array<Object>>}
         */
        async loadQuickReplies(options = {}) {
            this.loadingQuickReplies = true;
            try {
                const params = {};
                if (options.scope) { params.scope = options.scope; }
                if (options.q) { params.q = options.q; }
                if (options.sort) { params.sort = options.sort; }
                const { data } = await api.get('/inbox/quick-replies', { params });
                this.quickReplies = data.data ?? data;
                return this.quickReplies;
            } finally {
                this.loadingQuickReplies = false;
            }
        },

        /**
         * Cria uma resposta rápida.
         * @param {{ scope: string, shortcut: string, content: string }} payload
         * @returns {Promise<Object>}
         */
        async createQuickReply(payload) {
            const { data } = await api.post('/inbox/quick-replies', payload);
            const created = data.data ?? data;
            this.quickReplies = [...this.quickReplies, created];
            return created;
        },

        /**
         * Atualiza uma resposta rápida existente.
         * @param {number|string} id
         * @param {{ shortcut?: string, content?: string }} changes
         * @returns {Promise<Object>}
         */
        async updateQuickReply(id, changes) {
            const { data } = await api.patch(`/inbox/quick-replies/${id}`, changes);
            const updated = data.data ?? data;
            const idx = this.quickReplies.findIndex((r) => String(r.id) === String(id));
            if (idx !== -1) {
                this.quickReplies[idx] = updated;
            }
            return updated;
        },

        /**
         * Remove uma resposta rápida.
         * @param {number|string} id
         */
        async deleteQuickReply(id) {
            await api.delete(`/inbox/quick-replies/${id}`);
            this.quickReplies = this.quickReplies.filter((r) => String(r.id) !== String(id));
        },

        /**
         * Renderiza variáveis de uma resposta rápida no contexto de uma conversa (server-side).
         * @param {number|string} quickReplyId
         * @param {number|string} conversationId
         * @returns {Promise<{ rendered: string, variables_resolved: Record<string, string> }>}
         */
        async renderQuickReply(quickReplyId, conversationId) {
            const { data } = await api.post(`/inbox/quick-replies/${quickReplyId}/render`, {
                conversation_id: conversationId,
            });
            return data;
        },

        // ─── Helpers privados ─────────────────────────────────────────────────────

        /**
         * Adiciona uma mensagem ao array do id de conversa, evitando duplicatas.
         * @param {string} convId
         * @param {Object} message
         */
        _appendMessage(convId, message) {
            if (!this.messagesByConversationId[convId]) {
                this.messagesByConversationId[convId] = [];
            }
            const existing = this.messagesByConversationId[convId];
            const already = existing.some(
                (m) => String(m.id) === String(message.id),
            );
            if (!already) {
                existing.push(message);
            }
        },

        /**
         * Atualiza uma conversa na lista in-place.
         * @param {Object} updated
         */
        _updateConversationInList(updated) {
            if (!updated?.id) { return; }
            const idx = this.conversations.findIndex(
                (c) => String(c.id) === String(updated.id),
            );
            if (idx !== -1) {
                this.conversations[idx] = updated;
            }
        },
    },
});

import { defineStore } from 'pinia';
import api from '@/lib/api.js';

/**
 * Store Pinia para gestão de canais (WhatsApp, Instagram, Web Widget).
 *
 * Endpoints consumidos:
 *   GET    /inbox/channels
 *   POST   /inbox/channels
 *   GET    /inbox/channels/{id}
 *   PATCH  /inbox/channels/{id}
 *   DELETE /inbox/channels/{id}?force={bool}
 *   POST   /inbox/channels/{id}/reconnect
 *   GET    /inbox/channels/{id}/templates
 *   POST   /inbox/channels/{id}/templates/sync
 */
export const useCanaisStore = defineStore('canais', {
    state: () => ({
        /** @type {Array<import('../types/channel').Channel>} */
        channels: [],

        /** @type {import('../types/channel').Channel|null} */
        selectedChannel: null,

        /** @type {Record<string, Array>} cache de templates por channel id */
        templatesByChannel: {},

        loading: false,

        /** @type {string|null} */
        error: null,
    }),

    getters: {
        /**
         * Agrupa canais por type.
         * @returns {Record<string, Array>}
         */
        channelsByType: (state) => {
            return state.channels.reduce((acc, channel) => {
                const type = channel.type ?? 'unknown';
                if (!acc[type]) { acc[type] = []; }
                acc[type].push(channel);
                return acc;
            }, {});
        },

        /**
         * Contagem de canais com status=ativo.
         * @returns {number}
         */
        activeChannelsCount: (state) => {
            return state.channels.filter((c) => c.status === 'ativo').length;
        },

        /**
         * Canais em estado degradado (qualquer sinal de problema).
         * @returns {Array}
         */
        degradedChannels: (state) => {
            return state.channels.filter((c) =>
                ['degradado', 'expirado', 'invalido', 'suspenso'].includes(c.status),
            );
        },
    },

    actions: {
        /**
         * Carrega lista completa de canais do tenant.
         */
        async loadChannels() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get('/inbox/channels');
                this.channels = data.data ?? [];
            } catch (err) {
                this.error = err.response?.data?.message ?? 'Falha ao carregar canais.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Carrega detalhes de um canal específico.
         * @param {number|string} id
         */
        async loadChannel(id) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.get(`/inbox/channels/${id}`);
                this.selectedChannel = data.data ?? data;
                // Atualiza também na lista se já carregada
                const idx = this.channels.findIndex((c) => String(c.id) === String(id));
                if (idx !== -1) {
                    this.channels[idx] = this.selectedChannel;
                }
                return this.selectedChannel;
            } catch (err) {
                this.error = err.response?.data?.message ?? 'Falha ao carregar canal.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Conecta um novo canal.
         * @param {{ type: string, name: string, credentials: object }} payload
         * @returns {Promise<object>} channel criado
         */
        async connect(payload) {
            const { data } = await api.post('/inbox/channels', payload);
            const channel = data.data ?? data;
            this.channels.unshift(channel);
            return channel;
        },

        /**
         * Atualiza nome ou configurações de um canal.
         * @param {number|string} id
         * @param {{ name?: string, auto_send_disabled?: boolean }} payload
         */
        async update(id, payload) {
            const { data } = await api.patch(`/inbox/channels/${id}`, payload);
            const updated = data.data ?? data;
            const idx = this.channels.findIndex((c) => String(c.id) === String(id));
            if (idx !== -1) { this.channels[idx] = updated; }
            if (this.selectedChannel?.id === updated.id) { this.selectedChannel = updated; }
            return updated;
        },

        /**
         * Desconecta (soft delete) um canal.
         * @param {number|string} id
         * @param {boolean} force - passa ?force=true para forçar mesmo com conversas ativas
         */
        async disconnect(id, force = false) {
            await api.delete(`/inbox/channels/${id}`, { params: { force: force ? 'true' : undefined } });
            this.channels = this.channels.filter((c) => String(c.id) !== String(id));
            if (String(this.selectedChannel?.id) === String(id)) {
                this.selectedChannel = null;
            }
        },

        /**
         * Tenta reconectar um canal em status expirado/inválido.
         * @param {number|string} id
         * @param {object|undefined} credentialsOverride - novas credenciais opcionais
         */
        async reconnect(id, credentialsOverride = undefined) {
            const payload = credentialsOverride ? { credentials_override: credentialsOverride } : {};
            const { data } = await api.post(`/inbox/channels/${id}/reconnect`, payload);
            const updated = data.data ?? data;
            const idx = this.channels.findIndex((c) => String(c.id) === String(id));
            if (idx !== -1) { this.channels[idx] = updated; }
            if (String(this.selectedChannel?.id) === String(id)) { this.selectedChannel = updated; }
            return updated;
        },

        /**
         * Carrega templates de um canal (com cache por channelId).
         * @param {number|string} channelId
         * @param {boolean} forceRefresh - ignora cache
         */
        async loadTemplates(channelId, forceRefresh = false) {
            const key = String(channelId);
            if (!forceRefresh && this.templatesByChannel[key]) {
                return this.templatesByChannel[key];
            }
            const { data } = await api.get(`/inbox/channels/${channelId}/templates`);
            const templates = data.data ?? [];
            this.templatesByChannel[key] = templates;
            return templates;
        },

        /**
         * Força sincronização de templates via job Horizon.
         * @param {number|string} channelId
         * @returns {Promise<{ job_id: string, enqueued_at: string }>}
         */
        async syncTemplates(channelId) {
            const { data } = await api.post(`/inbox/channels/${channelId}/templates/sync`);
            // Invalida cache para forçar recarga após sync
            delete this.templatesByChannel[String(channelId)];
            return data;
        },

        // ─── Feature 014 — WhatsApp NÃO oficial (Evolution) ─────────────────────

        /**
         * Conecta um canal WhatsApp não oficial (Evolution) — cria a instância e
         * retorna o QR Code para pareamento.
         * @param {string} name
         * @returns {Promise<{ channel: object, qr: { base64: string|null, code: string|null, pairing_code: string|null }|null }>}
         */
        async connectEvolution(name) {
            const { data } = await api.post('/inbox/channels/evolution/connect', { name });
            const channel = data.data ?? data;
            this.channels.unshift(channel);
            return { channel, qr: data.qr ?? null };
        },

        /**
         * Regenera o QR Code de um canal Evolution (retoma o pareamento).
         * @param {number|string} id
         */
        async regenerateQr(id) {
            const { data } = await api.post(`/inbox/channels/${id}/qr`);
            return data.qr ?? null;
        },

        /**
         * Consulta o estado de conexão de um canal Evolution (para polling).
         * @param {number|string} id
         * @returns {Promise<{ channel_id: number, status: string }>}
         */
        async fetchConnectionState(id) {
            const { data } = await api.get(`/inbox/channels/${id}/connection-state`);
            const idx = this.channels.findIndex((c) => String(c.id) === String(id));
            if (idx !== -1) { this.channels[idx] = { ...this.channels[idx], status: data.status }; }
            return data;
        },
    },
});

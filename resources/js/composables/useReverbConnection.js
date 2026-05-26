import { ref, onUnmounted } from 'vue';

/**
 * Composable para gerenciar conexão com Laravel Echo/Reverb.
 *
 * Funcionalidades:
 * - Subscribe ao canal `private-tenant.{tenantId}.inbox` para eventos leves da lista.
 * - Subscribe/unsubscribe ao canal `private-tenant.{tenantId}.conversa.{cid}` por conversa.
 * - Backoff exponencial em caso de desconexão: 1→2→4→8→16→30s (NC-11.b).
 * - Heartbeat de 25s é gerenciado pelo Echo/Pusher nativo.
 * - Monitora estado de conexão em `connected` ref.
 *
 * @param {string|number} tenantId
 * @param {Function} onInboxEvent — callback para eventos do canal tenant.inbox
 * @param {Function} onConversationEvent — callback para eventos de conversa específica
 * @returns {{
 *   subscribeToConversation: (cid: string|number) => void,
 *   unsubscribeFromConversation: (cid: string|number) => void,
 *   connected: import('vue').Ref<boolean>,
 *   lastError: import('vue').Ref<string|null>,
 *   destroy: () => void,
 * }}
 */
export function useReverbConnection(tenantId, onInboxEvent, onConversationEvent) {
    /** @type {import('vue').Ref<boolean>} */
    const connected = ref(false);

    /** @type {import('vue').Ref<string|null>} */
    const lastError = ref(null);

    /** @type {Record<string, any>} canais de conversa ativos */
    const conversationChannels = {};

    let inboxChannel = null;
    let reconnectTimer = null;

    /** Sequência de delays de backoff em ms: 1→2→4→8→16→30s */
    const BACKOFF_DELAYS = [1000, 2000, 4000, 8000, 16000, 30000];
    let backoffIndex = 0;

    function getEcho() {
        return window.Echo ?? null;
    }

    function scheduleReconnect() {
        if (reconnectTimer) { return; }
        const delay = BACKOFF_DELAYS[Math.min(backoffIndex, BACKOFF_DELAYS.length - 1)];
        backoffIndex++;
        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            connectInbox();
        }, delay);
    }

    function connectInbox() {
        const echo = getEcho();
        if (!echo || !tenantId) { return; }

        try {
            const channelName = `tenant.${tenantId}.inbox`;

            inboxChannel = echo.private(channelName);

            // Nomes alinhados ao broadcastAs() do backend (dot-notation). O evento de
            // inbox serve só como sinal; o conteúdo é buscado via API (refetch).
            inboxChannel
                .listen('.mensagem.recebida', (event) => {
                    if (typeof onInboxEvent === 'function') {
                        onInboxEvent({ type: 'mensagem.recebida', payload: event });
                    }
                })
                .listen('.mensagem.enviada', (event) => {
                    if (typeof onInboxEvent === 'function') {
                        onInboxEvent({ type: 'mensagem.enviada', payload: event });
                    }
                })
                .listen('.conversa.criada', (event) => {
                    if (typeof onInboxEvent === 'function') {
                        onInboxEvent({ type: 'conversa.criada', payload: event });
                    }
                })
                .listen('.conversa.atribuida', (event) => {
                    if (typeof onInboxEvent === 'function') {
                        onInboxEvent({ type: 'conversa.atribuida', payload: event });
                    }
                })
                .error((error) => {
                    connected.value = false;
                    lastError.value = error?.message ?? 'Erro de conexão Reverb.';
                    scheduleReconnect();
                });

            // Monitora estado da conexão via Pusher socket
            const pusherConnection = echo.connector?.pusher?.connection;
            if (pusherConnection) {
                pusherConnection.bind('connected', () => {
                    connected.value = true;
                    backoffIndex = 0;
                    lastError.value = null;
                });

                pusherConnection.bind('disconnected', () => {
                    connected.value = false;
                    scheduleReconnect();
                });

                pusherConnection.bind('failed', () => {
                    connected.value = false;
                    lastError.value = 'Conexão WebSocket falhou.';
                    scheduleReconnect();
                });

                // Estado inicial
                if (pusherConnection.state === 'connected') {
                    connected.value = true;
                }
            }
        } catch (err) {
            connected.value = false;
            lastError.value = err?.message ?? 'Erro ao conectar ao Reverb.';
            scheduleReconnect();
        }
    }

    /**
     * Subscreve ao canal privado de uma conversa específica para eventos pesados
     * (typing, read receipts, status de mensagem).
     * @param {string|number} cid
     */
    function subscribeToConversation(cid) {
        const echo = getEcho();
        if (!echo || !tenantId || !cid) { return; }

        const key = String(cid);
        if (conversationChannels[key]) { return; } // Já subscrito

        try {
            const channelName = `tenant.${tenantId}.conversa.${cid}`;
            const ch = echo.private(channelName);

            ch.listen('.mensagem.recebida', (event) => {
                if (typeof onConversationEvent === 'function') {
                    onConversationEvent(cid, { type: 'mensagem.recebida', payload: event });
                }
            })
                .listen('.mensagem.enviada', (event) => {
                    if (typeof onConversationEvent === 'function') {
                        onConversationEvent(cid, { type: 'mensagem.enviada', payload: event });
                    }
                })
                .listen('.MensagemLida', (event) => {
                    if (typeof onConversationEvent === 'function') {
                        onConversationEvent(cid, { type: 'MensagemLida', payload: event });
                    }
                })
                .listen('.UsuarioDigitando', (event) => {
                    if (typeof onConversationEvent === 'function') {
                        onConversationEvent(cid, { type: 'UsuarioDigitando', payload: event });
                    }
                })
                .listen('.StatusMensagemAtualizado', (event) => {
                    if (typeof onConversationEvent === 'function') {
                        onConversationEvent(cid, {
                            type: 'StatusMensagemAtualizado',
                            payload: event,
                        });
                    }
                });

            conversationChannels[key] = ch;
        } catch {
            // Silencioso — não bloqueia UI
        }
    }

    /**
     * Remove subscription do canal de uma conversa específica.
     * @param {string|number} cid
     */
    function unsubscribeFromConversation(cid) {
        const echo = getEcho();
        const key = String(cid);

        if (!echo || !conversationChannels[key]) { return; }

        try {
            echo.leave(`tenant.${tenantId}.conversa.${cid}`);
        } catch {
            // Silencioso
        }
        delete conversationChannels[key];
    }

    /**
     * Remove todas as subscriptions e cancela timers.
     */
    function destroy() {
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }

        const echo = getEcho();
        if (echo) {
            // Desinscreve conversas individuais
            Object.keys(conversationChannels).forEach((cid) => {
                try {
                    echo.leave(`tenant.${tenantId}.conversa.${cid}`);
                } catch {
                    // Silencioso
                }
            });

            // Desinscreve canal de inbox
            try {
                echo.leave(`tenant.${tenantId}.inbox`);
            } catch {
                // Silencioso
            }
        }

        connected.value = false;
    }

    // Conecta ao montar
    connectInbox();

    // Auto-destroy no unmount
    onUnmounted(destroy);

    return {
        subscribeToConversation,
        unsubscribeFromConversation,
        connected,
        lastError,
        destroy,
    };
}

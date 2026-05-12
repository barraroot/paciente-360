import { ref, computed, watch, onUnmounted } from 'vue';

/**
 * Composable para long-polling fallback quando WebSocket falha (NC-11.c).
 *
 * Comportamento:
 * - Detecta `connected === false` por mais de 2 minutos.
 * - Ativa polling a cada 10s via GET /inbox/poll?since=<cursor>.
 * - Exibe banner de aviso enquanto polling está ativo.
 * - Desativa e remove banner quando Reverb reconecta.
 * - Em modo polling: typing e read receipts ficam desabilitados.
 *
 * @param {import('vue').Ref<boolean>} connected — ref do estado de conexão Reverb
 * @param {Function} pollFn — async function(since: string): Promise<string> — retorna next cursor
 * @param {string} initialCursor — ISO timestamp para começar o poll
 * @returns {{
 *   polling: import('vue').Ref<boolean>,
 *   banner: import('vue').ComputedRef<string|null>,
 *   disablePresenceFeatures: import('vue').ComputedRef<boolean>,
 * }}
 */
export function useLongPollingFallback(connected, pollFn, initialCursor = null) {
    /** @type {import('vue').Ref<boolean>} */
    const polling = ref(false);

    /** @type {import('vue').Ref<string>} */
    const lastCursor = ref(initialCursor ?? new Date().toISOString());

    /** Timer que detecta ausência de conexão por > 2 minutos */
    let disconnectedSince = null;
    let detectionTimer = null;
    let pollInterval = null;

    const DETECTION_THRESHOLD_MS = 2 * 60 * 1000; // 2 minutos
    const POLL_INTERVAL_MS = 10_000; // 10 segundos

    /**
     * Inicia o polling.
     */
    function startPolling() {
        if (polling.value) { return; }
        polling.value = true;

        pollInterval = setInterval(async () => {
            lastCursor.value = await pollFn(lastCursor.value);
        }, POLL_INTERVAL_MS);
    }

    /**
     * Para o polling (chamado quando Reverb reconecta).
     */
    function stopPolling() {
        if (!polling.value) { return; }
        polling.value = false;

        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    /**
     * Monitora o estado de conexão e ativa/desativa polling.
     */
    watch(
        connected,
        (isConnected) => {
            if (isConnected) {
                // Reconectou — para polling e limpa timer de detecção
                disconnectedSince = null;
                if (detectionTimer) {
                    clearTimeout(detectionTimer);
                    detectionTimer = null;
                }
                stopPolling();
            } else {
                // Desconectou — inicia contagem regressiva
                if (disconnectedSince === null) {
                    disconnectedSince = Date.now();
                    detectionTimer = setTimeout(() => {
                        startPolling();
                    }, DETECTION_THRESHOLD_MS);
                }
            }
        },
        { immediate: true },
    );

    /**
     * Banner informativo mostrado quando polling está ativo.
     * @type {import('vue').ComputedRef<string|null>}
     */
    const banner = computed(() => {
        if (!polling.value) { return null; }
        return 'inbox.fallback.modo_limitado';
    });

    /**
     * Indica se funcionalidades de presença (typing, read receipts) devem ser desabilitadas.
     * @type {import('vue').ComputedRef<boolean>}
     */
    const disablePresenceFeatures = computed(() => polling.value);

    /**
     * Cleanup ao desmontar o componente.
     */
    onUnmounted(() => {
        if (detectionTimer) {
            clearTimeout(detectionTimer);
            detectionTimer = null;
        }
        stopPolling();
    });

    return {
        polling,
        banner,
        disablePresenceFeatures,
    };
}

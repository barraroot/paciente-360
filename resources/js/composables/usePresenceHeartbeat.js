import { onBeforeUnmount, onMounted } from 'vue'
import axios from 'axios'

/**
 * T264 — Composable de heartbeat de presença (NC-6.b).
 *
 * Dispara POST /api/v1/inbox/presence/heartbeat a cada 60 segundos
 * enquanto o componente que usa este composable estiver montado.
 *
 * Uso: import e chame `usePresenceHeartbeat()` no setup() de Inbox/Index.vue.
 * O intervalo é limpo automaticamente em onBeforeUnmount.
 */
export function usePresenceHeartbeat(intervalSeconds = 60) {
    let intervalId = null

    const sendHeartbeat = async () => {
        try {
            await axios.post('/api/v1/inbox/presence/heartbeat')
        } catch (error) {
            // Falha silenciosa — não interrompe UX; servidor marcará offline após idle threshold
            if (import.meta.env.DEV) {
                console.warn('[usePresenceHeartbeat] heartbeat failed:', error?.message)
            }
        }
    }

    onMounted(() => {
        // Envia imediatamente ao montar
        sendHeartbeat()

        intervalId = setInterval(sendHeartbeat, intervalSeconds * 1000)
    })

    onBeforeUnmount(() => {
        if (intervalId !== null) {
            clearInterval(intervalId)
            intervalId = null
        }
    })

    return { sendHeartbeat }
}

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Echo client com authorizer Bearer (Fase 4 Lote F — T062).
 *
 * O `/broadcasting/auth` endpoint passou a exigir auth:sanctum + tenant.slug
 * (ver bootstrap/app.php → withBroadcasting). Não enviamos mais cookie de
 * sessão / CSRF — todo o auth é via Authorization Bearer + X-Tenant-Slug,
 * resolvidos via store Pinia em runtime.
 *
 * O `authorizer` recebe `socket_id` + `channel.name` da própria lib Pusher e
 * espera que `callback(null, authPayload)` seja chamado com o payload assinado
 * pelo servidor (`{auth: "<key>:<hmac>"}`).
 *
 * Lazy import do auth store + api wrapper para evitar:
 *  - boot da Pinia antes do `createPinia()` em app.js
 *  - ciclo `echo.js → api.js → stores/auth.js → ... → echo.js`
 */
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
        authorize: async (socketId, callback) => {
            try {
                const { default: api } = await import('@/lib/api.js');

                // O interceptor de request em api.js já injeta:
                //   - Authorization: Bearer <token>
                //   - X-Tenant-Slug: <slug>
                //   - X-Request-Id
                // Por isso usamos a instância pré-configurada em vez do axios global.
                const response = await api.post(
                    '/broadcasting/auth',
                    {
                        socket_id: socketId,
                        channel_name: channel.name,
                    },
                    {
                        // O endpoint /broadcasting/auth não está sob /api/v1 —
                        // sobrescrevemos baseURL para apontar à raiz.
                        baseURL: '/',
                    },
                );

                callback(null, response.data);
            } catch (error) {
                if (import.meta.env.DEV) {
                    console.error(
                        'Echo authorizer falhou:',
                        error.response?.status,
                        error.response?.data,
                    );
                }
                callback(error, null);
            }
        },
    }),
});

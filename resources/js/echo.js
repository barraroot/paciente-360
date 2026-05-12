import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;

/**
 * Configurar defaults globais do axios para Sanctum SPA stateful.
 *
 * Necessário porque o `authorizer` do Echo recebe o axios global (não a
 * instância pré-configurada em `lib/api.js`), e o cliente Pusher dispara
 * `POST /broadcasting/auth` que PRECISA enviar o cookie de sessão + CSRF.
 *
 * Sem `withCredentials`, o cookie não é enviado → backend responde 403
 * porque `auth()->user()` retorna null.
 */
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            axios
                .post(
                    '/broadcasting/auth',
                    {
                        socket_id: socketId,
                        channel_name: channel.name,
                    },
                    {
                        headers: { Accept: 'application/json' },
                    },
                )
                .then((response) => callback(null, response.data))
                .catch((error) => {
                    console.error('Echo authorizer falhou:', error.response?.status, error.response?.data);
                    callback(error, null);
                });
        },
    }),
});

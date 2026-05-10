import axios from 'axios';

/**
 * Instância Axios pré-configurada para o backend Laravel/Sanctum SPA.
 *
 * - baseURL aponta para /api/v1 (versionado).
 * - withCredentials envia cookies de sessão Sanctum.
 * - withXSRFToken (Axios ≥1.x) lê automaticamente o cookie XSRF-TOKEN
 *   e injeta no header X-XSRF-TOKEN.
 * - Interceptor de request injeta X-Request-Id para rastreabilidade.
 * - Interceptor de response trata 401 (sessão expirada) com logout + redirect
 *   usando lazy import para evitar ciclo de dependência circular.
 */
const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
});

/**
 * Helper para buscar o cookie CSRF antes de operações stateful (login).
 * O endpoint /sanctum/csrf-cookie fica em / (não em /api/v1), por isso
 * usa o axios global em vez da instância api.
 */
api.getCsrfCookie = () =>
    axios.get('/sanctum/csrf-cookie', { withCredentials: true });

/**
 * Gera um identificador de requisição no formato ULID simplificado:
 * timestamp base-36 + 8 chars aleatórios, em maiúsculas.
 */
function generateRequestId() {
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`.toUpperCase();
}

// Interceptor de request — injeta X-Request-Id em toda requisição.
api.interceptors.request.use((config) => {
    config.headers['X-Request-Id'] = generateRequestId();
    return config;
});

// Interceptor de response — 401 dispara logout + redirect para /login
// usando lazy import para quebrar o ciclo: api ← store ← api.
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status;

        if (status === 401) {
            const { useAuthStore } = await import('@/stores/auth.js');
            const { default: router } = await import('@/router/index.js');

            useAuthStore().reset();

            if (router.currentRoute.value.name !== 'auth.login') {
                router.push({
                    name: 'auth.login',
                    query: { redirect: router.currentRoute.value.fullPath },
                });
            }
        }

        return Promise.reject(error);
    },
);

export default api;

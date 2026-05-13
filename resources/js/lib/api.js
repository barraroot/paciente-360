import axios from "axios";

/**
 * Instância Axios pré-configurada para o backend Laravel/Sanctum Bearer.
 *
 * - baseURL aponta para /api/v1 (versionado).
 * - Interceptor de request injeta:
 *   - X-Request-Id para rastreabilidade
 *   - Authorization: Bearer <token> quando autenticado
 *   - X-Tenant-Slug para triple-check anti-cross-tenant (FR-011)
 * - Interceptor de response trata 401 (token expirado/revogado):
 *   reset store + redirect /login com query redirect.
 *
 * Lazy imports para evitar ciclo de dependência circular:
 *   api.js ← importa → stores/auth.js ← importa → api.js
 */
const api = axios.create({
    baseURL: "/api/v1",
    headers: { Accept: "application/json" },
});

/**
 * Gera um identificador de requisição no formato ULID simplificado:
 * timestamp base-36 + 8 chars aleatórios, em maiúsculas.
 */
function generateRequestId() {
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`.toUpperCase();
}

// Interceptor de request — injeta X-Request-Id, Authorization Bearer e X-Tenant-Slug.
api.interceptors.request.use(async (config) => {
    config.headers["X-Request-Id"] = generateRequestId();

    const { useAuthStore } = await import("@/stores/auth.js");
    const authStore = useAuthStore();

    if (authStore.token) {
        config.headers["Authorization"] = `Bearer ${authStore.token}`;
    }

    if (authStore.tenant?.slug) {
        config.headers["X-Tenant-Slug"] = authStore.tenant.slug;
    }

    return config;
});

// Interceptor de response — 401 dispara reset + redirect para /login.
// Usa lazy import para quebrar o ciclo: api ← store ← api.
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status;

        if (status === 401) {
            const { useAuthStore } = await import("@/stores/auth.js");
            const { default: router } = await import("@/router/index.js");

            useAuthStore().reset();

            if (router.currentRoute.value.name !== "auth.login") {
                router.push({
                    name: "auth.login",
                    query: { redirect: router.currentRoute.value.fullPath },
                });
            }
        }

        return Promise.reject(error);
    },
);

export default api;

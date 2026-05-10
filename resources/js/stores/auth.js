import { defineStore } from 'pinia';

/**
 * Store de autenticação Sanctum SPA.
 *
 * State: user, tenant, permissions.
 * Getters: isAuthenticated, currentTenantId.
 * Actions base: setUser, setTenant, setPermissions, reset.
 * Actions de sessão (T107/T108): login, logout, fetchMe.
 * Helpers: hasPermission, hasRole.
 *
 * O api é importado via lazy import dentro de cada action para evitar
 * ciclo de dependência circular (api.js importa esta store no interceptor
 * de 401).
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        tenant: null,
        permissions: [],
    }),
    getters: {
        isAuthenticated: (state) => state.user !== null,
        currentTenantId: (state) => state.tenant?.id ?? null,
    },
    actions: {
        setUser(user) {
            this.user = user;
        },
        setTenant(tenant) {
            this.tenant = tenant;
        },
        setPermissions(permissions) {
            this.permissions = Array.isArray(permissions) ? permissions : [];
        },
        reset() {
            this.user = null;
            this.tenant = null;
            this.permissions = [];
        },

        /**
         * Autentica o usuário via Sanctum SPA stateful.
         * 1. Busca o cookie CSRF em /sanctum/csrf-cookie.
         * 2. Faz POST /api/v1/auth/login.
         * 3. Popula state com user, tenant e permissions.
         *
         * @param {{ email: string, password: string, remember?: boolean }} credentials
         * @returns {Promise<object>} dados do usuário autenticado
         */
        async login({ email, password, remember = false }) {
            const api = (await import('@/lib/api.js')).default;
            await api.getCsrfCookie();
            const { data } = await api.post('/auth/login', { email, password, remember });
            this.setUser(data.data);
            this.setTenant(data.data.tenant);
            this.setPermissions(data.data.permissions);
            return data.data;
        },

        /**
         * Encerra a sessão no backend e limpa o state local.
         * Idempotente: falhas na API são ignoradas (sessão já pode ter expirado).
         */
        async logout() {
            const api = (await import('@/lib/api.js')).default;
            try {
                await api.post('/auth/logout');
            } catch {
                // Idempotente — sessão pode já estar expirada no servidor.
            }
            this.reset();
        },

        /**
         * Rehidrata o state a partir da sessão ativa no cookie.
         * Chamado pelo guard do router ao navegar para rota requiresAuth
         * sem isAuthenticated (ex.: F5 recarrega a página).
         *
         * @returns {Promise<object>} dados do usuário
         */
        async fetchMe() {
            const api = (await import('@/lib/api.js')).default;
            const { data } = await api.get('/auth/me');
            this.setUser(data.data);
            this.setTenant(data.data.tenant);
            this.setPermissions(data.data.permissions);
            return data.data;
        },

        /**
         * Verifica se o usuário possui determinada permissão.
         *
         * @param {string} name
         * @returns {boolean}
         */
        hasPermission(name) {
            return this.permissions.includes(name);
        },

        /**
         * Verifica se o usuário possui determinado papel (role).
         *
         * @param {string} name
         * @returns {boolean}
         */
        hasRole(name) {
            return (this.user?.roles ?? []).includes(name);
        },
    },
});

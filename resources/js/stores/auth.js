import { defineStore } from 'pinia';

/**
 * Store de autenticação Bearer Token (Sanctum Personal Access Tokens).
 *
 * State: token, user, tenant, permissions.
 * Getters: isAuthenticated, currentTenantId.
 * Actions base (API pública preservada): setUser, setTenant, setPermissions, reset.
 * Actions de sessão: boot, login, logout, logoutAll, fetchMe.
 * Helpers preservados: hasPermission, hasRole.
 * Helpers internos: setToken, clearToken.
 *
 * Fluxo de autenticação:
 *  1. app.js chama authStore.boot() antes de montar o app.
 *  2. boot() lê token de localStorage; se presente, chama fetchMe() para revalidar.
 *  3. login() POST /auth/login → persiste token + popula state.
 *  4. logout() POST /auth/logout → limpa localStorage + state.
 *  5. 401 interceptor em api.js chama reset() diretamente (sem API call).
 *
 * O api.js importa esta store via lazy import dentro do interceptor de 401
 * para evitar ciclo de dependência circular.
 *
 * localStorage keys (NC-3):
 *  - paciente360.auth.token        — plain token string
 *  - paciente360.auth.tenant_slug  — cached slug para X-Tenant-Slug header
 */

const LS_TOKEN_KEY = 'paciente360.auth.token';
const LS_TENANT_SLUG_KEY = 'paciente360.auth.tenant_slug';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: null,
        user: null,
        tenant: null,
        permissions: [],
    }),

    getters: {
        /**
         * Usuário considerado autenticado apenas se token E user estão presentes.
         * Durante o boot (token presente mas fetchMe ainda não completou) retorna false
         * até a rehidratação terminar.
         */
        isAuthenticated: (state) => state.token !== null && state.user !== null,

        currentTenantId: (state) => state.tenant?.id ?? null,
    },

    actions: {
        // ─── Setters públicos (API preservada — pages existentes dependem) ─────────

        setUser(user) {
            this.user = user;
        },

        setTenant(tenant) {
            this.tenant = tenant;
            // Persiste slug para que o próximo boot já possa enviar X-Tenant-Slug
            // no primeiro request (GET /auth/me) mesmo antes do fetchMe responder.
            if (tenant?.slug) {
                try {
                    localStorage.setItem(LS_TENANT_SLUG_KEY, tenant.slug);
                } catch {
                    // Safari private mode pode lançar; silencioso.
                }
            }
        },

        setPermissions(permissions) {
            this.permissions = Array.isArray(permissions) ? permissions : [];
        },

        // ─── Helpers de token (internos, mas exportados para testes) ─────────────

        setToken(token) {
            this.token = token;
            try {
                localStorage.setItem(LS_TOKEN_KEY, token);
            } catch {
                // Silencioso em contextos sem localStorage.
            }
        },

        clearToken() {
            this.token = null;
            try {
                localStorage.removeItem(LS_TOKEN_KEY);
                localStorage.removeItem(LS_TENANT_SLUG_KEY);
            } catch {
                // Silencioso.
            }
        },

        // ─── Reset (usado pelo interceptor 401 — não chama API) ──────────────────

        /**
         * Limpa todo o state local SEM chamar qualquer endpoint.
         * Chamado pelo interceptor de 401 em api.js para evitar loop infinito.
         */
        reset() {
            this.clearToken();
            this.user = null;
            this.tenant = null;
            this.permissions = [];
        },

        // ─── Boot — executado antes do mount do app ───────────────────────────────

        /**
         * Rehidrata a sessão a partir do token persistido em localStorage.
         *
         * Fluxo:
         *  1. Lê token de localStorage.
         *  2. Se ausente → noop (app monta deslogado).
         *  3. Se presente → seta token em state (para que o interceptor de request
         *     já injete Authorization no fetchMe); pré-carrega tenant_slug também.
         *  4. Chama fetchMe() para revalidar token no servidor.
         *  5. Se fetchMe falhar (401/403/network) → clearToken() silencioso;
         *     app monta deslogado; interceptor de 401 redireciona via router.
         *
         * Nunca joga exceção — sempre resolve (finally).
         *
         * @returns {Promise<void>}
         */
        async boot() {
            let storedToken = null;
            let storedSlug = null;

            try {
                storedToken = localStorage.getItem(LS_TOKEN_KEY);
                storedSlug = localStorage.getItem(LS_TENANT_SLUG_KEY);
            } catch {
                // localStorage indisponível.
            }

            if (!storedToken) {
                return;
            }

            // Seta token e slug antecipadamente para que o primeiro request (fetchMe)
            // já vá com os headers corretos.
            this.token = storedToken;
            if (storedSlug) {
                // Popula tenant parcialmente com só o slug para que o interceptor
                // de request possa construir o header X-Tenant-Slug.
                this.tenant = this.tenant ?? { slug: storedSlug };
            }

            try {
                await this.fetchMe();
            } catch {
                // Token revogado, expirado ou rede offline — limpa silenciosamente.
                this.clearToken();
                this.user = null;
                this.tenant = null;
                this.permissions = [];
            }
        },

        // ─── Login ────────────────────────────────────────────────────────────────

        /**
         * Autentica o usuário via Bearer.
         * POST /auth/login → recebe {token, token_expires_at, user, tenant}.
         * Persiste token + slug em localStorage.
         *
         * @param {{ email: string, password: string, device_name?: string }} credentials
         * @returns {Promise<object>} dados do usuário autenticado
         */
        async login({ email, password, device_name }) {
            const api = (await import('@/lib/api.js')).default;
            const { data } = await api.post('/auth/login', {
                email,
                password,
                device_name,
            });

            this.setToken(data.token);
            this.setUser(data.user);
            this.setTenant(data.tenant);

            // Permissões podem vir embutidas no user ou via fetchMe.
            const permissions = data.user?.permissions ?? [];
            this.setPermissions(permissions);

            // Se o backend não enviou permissions no login, busca via /me.
            if (permissions.length === 0) {
                try {
                    await this.fetchMe();
                } catch {
                    // Não bloqueia o login se fetchMe falhar aqui.
                }
            }

            return data.user;
        },

        // ─── Logout ───────────────────────────────────────────────────────────────

        /**
         * Revoga o token atual no servidor e limpa o state local.
         * Idempotente: falhas na API são ignoradas.
         */
        async logout() {
            const api = (await import('@/lib/api.js')).default;
            try {
                await api.post('/auth/logout');
            } catch {
                // Idempotente — token pode já estar revogado.
            }
            this.reset();
        },

        /**
         * Revoga TODOS os tokens do usuário e limpa o state local.
         * Usado em "Sair de todos os dispositivos".
         */
        async logoutAll() {
            const api = (await import('@/lib/api.js')).default;
            try {
                await api.post('/auth/logout-all');
            } catch {
                // Idempotente.
            }
            this.reset();
        },

        // ─── fetchMe ──────────────────────────────────────────────────────────────

        /**
         * Rehidrata o state a partir do endpoint /auth/me.
         * Chamado no boot() e potencialmente pelo router guard.
         *
         * Popula user, tenant, permissions e atualiza o slug cacheado.
         *
         * @returns {Promise<object>} dados do usuário
         */
        async fetchMe() {
            const api = (await import('@/lib/api.js')).default;
            const { data } = await api.get('/auth/me');

            this.setUser(data.user);
            this.setTenant(data.tenant);
            this.setPermissions(data.user?.permissions ?? []);

            return data.user;
        },

        // ─── Helpers de permissão (API pública preservada) ───────────────────────

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

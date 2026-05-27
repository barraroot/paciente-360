import { type APIRequestContext, type Page, request as pwRequest } from '@playwright/test';

/**
 * Auth helper para o sweep de auditoria (feature 016).
 *
 * A SPA usa Bearer token Sanctum (Fase 4): token em localStorage
 * `paciente360.auth.token` + slug em `paciente360.auth.tenant_slug`.
 * Aqui logamos via API e injetamos o token no localStorage da página
 * (`addInitScript`) antes de navegar — evita dirigir o formulário a cada teste.
 */
export const TENANT_SLUG = process.env.UX_TENANT_SLUG ?? 'clinica-alfa';
export const BASE_URL =
    process.env.PLAYWRIGHT_BASE_URL ?? `https://${TENANT_SLUG}.paciente-360.com`;

const LS_TOKEN_KEY = 'paciente360.auth.token';
const LS_TENANT_SLUG_KEY = 'paciente360.auth.tenant_slug';

export type Persona = 'admin' | 'medico' | 'atendente' | 'recepcionista';

const PERSONA_EMAIL: Record<Persona, string> = {
    admin: `admin@${TENANT_SLUG}.test`,
    medico: `medico@${TENANT_SLUG}.test`,
    atendente: `atendente@${TENANT_SLUG}.test`,
    recepcionista: `recepcionista@${TENANT_SLUG}.test`,
};

/** Faz login via API e devolve o plain token. */
export async function loginToken(persona: Persona = 'admin'): Promise<string> {
    const ctx: APIRequestContext = await pwRequest.newContext({
        baseURL: BASE_URL,
        ignoreHTTPSErrors: true,
    });
    const res = await ctx.post('/api/v1/auth/login', {
        headers: { 'X-Tenant-Slug': TENANT_SLUG, Accept: 'application/json' },
        data: { email: PERSONA_EMAIL[persona], password: 'password123' },
    });
    if (!res.ok()) {
        throw new Error(`login ${persona} falhou: ${res.status()} ${await res.text()}`);
    }
    const body = await res.json();
    await ctx.dispose();
    return body.token as string;
}

/** Injeta o token no localStorage da página para todas as navegações subsequentes. */
export async function authenticatePage(page: Page, token: string): Promise<void> {
    await page.addInitScript(
        ([tokenKey, slugKey, tokenVal, slugVal]) => {
            try {
                localStorage.setItem(tokenKey as string, tokenVal as string);
                localStorage.setItem(slugKey as string, slugVal as string);
            } catch {
                /* sem localStorage — ignora */
            }
        },
        [LS_TOKEN_KEY, LS_TENANT_SLUG_KEY, token, TENANT_SLUG],
    );
}

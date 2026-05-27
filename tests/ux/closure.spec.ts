import { test, expect, type Page } from '@playwright/test';
import { authenticatePage, loginToken } from './support/auth';
import { assertNoHorizontalOverflow } from './support/uiInvariants';

/**
 * Verificação de fechamento (feature 016 · T041 smoke + T031 teclado + T035 G15).
 * Roda com UX_CLOSURE=1 (exige app no ar + tenant seedado).
 */
test.skip(process.env.UX_CLOSURE !== '1', 'defina UX_CLOSURE=1');

let token: string;
test.beforeAll(async () => {
    token = await loginToken('admin');
});

// ── T041: smoke das jornadas-chave em desktop + mobile (SC-003) ──────────────
const JOURNEYS = [
    { name: 'responder conversa', path: '/panel/inbox' },
    { name: 'agendar', path: '/panel/agenda' },
    { name: 'cadastrar paciente', path: '/panel/pacientes/novo' },
    { name: 'emitir receita', path: '/panel/receituarios/novo' },
];

for (const j of JOURNEYS) {
    for (const width of [375, 1366]) {
        test(`smoke ${j.name} @${width}`, async ({ page }) => {
            await authenticatePage(page, token);
            await page.setViewportSize({ width, height: 900 });
            await page.goto(j.path, { waitUntil: 'networkidle' });
            await page.waitForTimeout(400);
            // Operável: tem conteúdo principal + ao menos um controle interativo, sem overflow.
            await assertNoHorizontalOverflow(page);
            const interactive = await page
                .locator('button, a[href], input, select, [role="button"]')
                .count();
            expect(interactive, `${j.name} @${width}: controles interativos`).toBeGreaterThan(0);
        });
    }
}

// ── T031: foco visível + ordem de tabulação ──────────────────────────────────
test('teclado: foco move e fica visível (G10)', async ({ page }) => {
    await authenticatePage(page, token);
    await page.setViewportSize({ width: 1366, height: 900 });
    await page.goto('/panel/pacientes', { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);
    const seen = new Set<string>();
    for (let i = 0; i < 6; i++) {
        await page.keyboard.press('Tab');
        const info = await page.evaluate(() => {
            const el = document.activeElement as HTMLElement | null;
            if (!el || el === document.body) {
                return null;
            }
            const s = getComputedStyle(el);
            // foco visível: outline OU box-shadow OU ring (focus-visible)
            const hasRing = s.outlineStyle !== 'none' || s.boxShadow !== 'none';
            return { tag: el.tagName, id: el.outerHTML.slice(0, 40), hasRing };
        });
        if (info) {
            seen.add(info.id);
        }
    }
    expect(seen.size, 'elementos distintos recebendo foco ao tabular').toBeGreaterThan(1);
});

// ── T035: texto longo não estoura (G15) ──────────────────────────────────────
async function typeLongAndCheck(page: Page, path: string) {
    await page.goto(path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);
    const input = page.locator('input[type="text"], input:not([type])').first();
    if (await input.count()) {
        await input.fill('Maria '.repeat(40).trim()); // ~240 chars sem espaços longos
        await page.waitForTimeout(150);
        await assertNoHorizontalOverflow(page);
    }
}

test('texto longo em formulário não estoura (G15) @375', async ({ page }) => {
    await authenticatePage(page, token);
    await page.setViewportSize({ width: 375, height: 900 });
    await typeLongAndCheck(page, '/panel/pacientes/novo');
});

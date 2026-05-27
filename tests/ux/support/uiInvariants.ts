import { expect, type Page, type Locator } from '@playwright/test';

/**
 * Helpers de invariantes de UI (feature 016 — gates G1–G7).
 *
 * Reutilizáveis por tela/largura nas specs de auditoria. Medem geometria em
 * runtime para tornar os critérios da spec verificáveis e prevenir regressão.
 */

/** Larguras amostradas na faixa contínua ~320→≥1920px (FR-014 / Clarification Q4). */
export const SAMPLE_WIDTHS = [320, 375, 768, 1024, 1366, 1440, 1880, 1920] as const;

/** G1 — sem overflow horizontal indevido no documento. */
export async function assertNoHorizontalOverflow(page: Page, tolerance = 1): Promise<void> {
    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow, 'overflow horizontal do documento (px)').toBeLessThanOrEqual(tolerance);
}

/** G2 — o elemento preenche até a borda direita da viewport (tolerância px). */
export async function assertFillsRight(locator: Locator, tolerance = 1): Promise<void> {
    const box = await locator.boundingBox();
    const vw = await locator.page().evaluate(() => window.innerWidth);
    expect(box, 'boundingBox do elemento').not.toBeNull();
    expect(Math.abs((box!.x + box!.width) - vw), 'distância da borda direita (px)').toBeLessThanOrEqual(tolerance);
}

/** G2 — o elemento preenche até o rodapé da viewport (tolerância px). */
export async function assertFillsBottom(locator: Locator, tolerance = 1): Promise<void> {
    const box = await locator.boundingBox();
    const vh = await locator.page().evaluate(() => window.innerHeight);
    expect(box, 'boundingBox do elemento').not.toBeNull();
    expect(Math.abs((box!.y + box!.height) - vh), 'distância do rodapé (px)').toBeLessThanOrEqual(tolerance);
}

/** G3 — o elemento está totalmente contido na viewport (não cortado). */
export async function assertWithinViewport(locator: Locator, tolerance = 1): Promise<void> {
    const box = await locator.boundingBox();
    const { vw, vh } = await locator.page().evaluate(() => ({ vw: window.innerWidth, vh: window.innerHeight }));
    expect(box).not.toBeNull();
    expect(box!.x, 'left').toBeGreaterThanOrEqual(-tolerance);
    expect(box!.y, 'top').toBeGreaterThanOrEqual(-tolerance);
    expect(box!.x + box!.width, 'right').toBeLessThanOrEqual(vw + tolerance);
    expect(box!.y + box!.height, 'bottom').toBeLessThanOrEqual(vh + tolerance);
}

/** G3 — nenhum container interno relevante estoura sua largura (scrollWidth > clientWidth). */
export async function assertNoChildOverflow(page: Page, selector: string, tolerance = 1): Promise<void> {
    const offenders = await page.evaluate(
        ([sel, tol]) => [...document.querySelectorAll(sel as string)]
            .filter((el) => el.scrollWidth - el.clientWidth > (tol as number))
            .map((el) => el.className?.toString().slice(0, 60) ?? el.tagName),
        [selector, tolerance],
    );
    expect(offenders, `containers com overflow-x (${selector})`).toEqual([]);
}

/** G6 — alvos de toque com área mínima (~44×44px) nas larguras pequenas. */
export async function assertTouchTargets(page: Page, selector = 'button, a, [role="button"]', min = 40): Promise<void> {
    const small = await page.evaluate(
        ([sel, m]) => [...document.querySelectorAll(sel as string)]
            .filter((el) => {
                const r = el.getBoundingClientRect();
                return r.width > 0 && r.height > 0 && (r.width < (m as number) || r.height < (m as number));
            })
            .map((el) => (el.textContent ?? '').trim().slice(0, 30) || el.className?.toString().slice(0, 40)),
        [selector, min],
    );
    expect(small, 'alvos de toque abaixo do mínimo').toEqual([]);
}

/** G7 — modal aberto cabe na viewport. */
export async function assertModalFits(modal: Locator, tolerance = 2): Promise<void> {
    await assertWithinViewport(modal, tolerance);
}

import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { authenticatePage, loginToken } from './support/auth';
import { ALL_ROUTES } from './support/routes';
import { assertNoHorizontalOverflow } from './support/uiInvariants';

/**
 * Gate de regressão de invariantes de UI (feature 016 · T038 / FR-015).
 *
 * Diferente do `audit-sweep.spec.ts` (report-mode, não falha): ESTE **falha**
 * quando uma tela regride — overflow horizontal (G1/G3/G5) ou violação axe
 * serious/critical (G10/G11/G12). É o gate permanente de CI.
 *
 * Larguras de regressão (subset representativo p/ manter o gate rápido):
 * 375 (mobile), 768 (tablet), 1366 (desktop). axe em 375 + 1366.
 * Roda só com UX_GATE=1 (exige app no ar + base URL do tenant).
 */
const GATE_WIDTHS = [375, 768, 1366];
const AXE_WIDTHS = [375, 1366];
const HEIGHT = 900;

async function childOverflowOffenders(page: Page): Promise<string[]> {
    return page.evaluate((tol) => {
        return [...document.querySelectorAll<HTMLElement>('*')]
            .filter((el) => {
                const style = getComputedStyle(el);
                const scrollable =
                    style.overflowX === 'auto' || style.overflowX === 'scroll' || style.overflowX === 'hidden';
                return !scrollable && el.scrollWidth - el.clientWidth > tol && el.clientWidth > 0;
            })
            .slice(0, 6)
            .map((el) => {
                const cls = typeof el.className === 'string' ? el.className.slice(0, 50) : el.tagName;
                return `${el.tagName.toLowerCase()}.${cls} (+${el.scrollWidth - el.clientWidth}px)`;
            });
    }, 2);
}

test.describe('UX invariants gate', () => {
    test.skip(process.env.UX_GATE !== '1', 'defina UX_GATE=1 (com app no ar) para o gate de regressão');

    let token: string;
    test.beforeAll(async () => {
        token = await loginToken('admin');
    });

    for (const route of ALL_ROUTES) {
        test(`${route.priority} ${route.path}`, async ({ page }) => {
            if (route.auth) {
                await authenticatePage(page, token);
            }

            for (const width of GATE_WIDTHS) {
                await page.setViewportSize({ width, height: HEIGHT });
                await page.goto(route.path, { waitUntil: 'networkidle' });
                await page.waitForTimeout(300);

                await assertNoHorizontalOverflow(page);
                const offenders = await childOverflowOffenders(page);
                expect(offenders, `${route.path} @${width}: overflow de container interno`).toEqual([]);

                if (AXE_WIDTHS.includes(width)) {
                    const results = await new AxeBuilder({ page })
                        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
                        .analyze();
                    const blocking = results.violations
                        .filter((v) => v.impact === 'serious' || v.impact === 'critical')
                        .map((v) => `${v.id} (${v.impact}) ×${v.nodes.length}`);
                    expect(blocking, `${route.path} @${width}: violações axe serious/critical`).toEqual([]);
                }
            }
        });
    }
});

import { test, expect, type Page } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import AxeBuilder from '@axe-core/playwright';
import { authenticatePage, loginToken } from './support/auth';
import { ALL_ROUTES, type Auditable } from './support/routes';
import { SAMPLE_WIDTHS } from './support/uiInvariants';

/**
 * Sweep de auditoria (feature 016 · T008–T010) em MODO RELATÓRIO.
 *
 * Não falha por achado — coleta defeitos de geometria (G1 overflow do documento,
 * G3 overflow de container interno) em todas as larguras amostradas e violações
 * axe serious/critical (G11/G12) em 375 + 1366, escrevendo
 * `specs/016-frontend-ux-audit/audit-findings.json` para síntese no catálogo.
 *
 * Roda só quando UX_SWEEP=1 (evita disparar no CI de regressão por engano).
 */
const SWEEP = process.env.UX_SWEEP === '1';
const AXE_WIDTHS = [375, 1366];
const HEIGHT = 900;

type Finding = {
    route: string;
    name: string;
    priority: string;
    docOverflow: Record<number, number>; // width -> px de overflow do documento
    childOverflow: { width: number; offenders: string[] }[];
    axe: { width: number; violations: { id: string; impact: string; nodes: number }[] }[];
    error?: string;
};

const findings: Finding[] = [];

async function measure(page: Page, route: Auditable): Promise<Finding> {
    const f: Finding = {
        route: route.path,
        name: route.name,
        priority: route.priority,
        docOverflow: {},
        childOverflow: [],
        axe: [],
    };

    for (const width of SAMPLE_WIDTHS) {
        await page.setViewportSize({ width, height: HEIGHT });
        await page.goto(route.path, { waitUntil: 'networkidle' }).catch(() => {});
        await page.waitForTimeout(400);

        const docOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth - window.innerWidth,
        );
        f.docOverflow[width] = docOverflow;

        const offenders = await page.evaluate((tol) => {
            return [...document.querySelectorAll<HTMLElement>('*')]
                .filter((el) => {
                    const style = getComputedStyle(el);
                    const scrollable =
                        style.overflowX === 'auto' || style.overflowX === 'scroll' || style.overflowX === 'hidden';
                    return !scrollable && el.scrollWidth - el.clientWidth > tol && el.clientWidth > 0;
                })
                .slice(0, 8)
                .map((el) => {
                    const cls = typeof el.className === 'string' ? el.className.slice(0, 50) : el.tagName;
                    return `${el.tagName.toLowerCase()}.${cls} (+${el.scrollWidth - el.clientWidth}px)`;
                });
        }, 2);
        if (offenders.length > 0) {
            f.childOverflow.push({ width, offenders });
        }

        if (AXE_WIDTHS.includes(width)) {
            try {
                const results = await new AxeBuilder({ page })
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
                    .analyze();
                const blocking = results.violations
                    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
                    .map((v) => ({ id: v.id, impact: v.impact ?? '', nodes: v.nodes.length }));
                if (blocking.length > 0) {
                    f.axe.push({ width, violations: blocking });
                }
            } catch (e) {
                /* axe pode falhar em página sem conteúdo — ignora */
            }
        }
    }
    return f;
}

test.describe('UX audit sweep (report mode)', () => {
    test.skip(!SWEEP, 'defina UX_SWEEP=1 para rodar o sweep de auditoria');

    let token: string;

    test.beforeAll(async () => {
        token = await loginToken('admin');
    });

    for (const route of ALL_ROUTES) {
        test(`sweep ${route.priority} ${route.path}`, async ({ page }) => {
            if (route.auth) {
                await authenticatePage(page, token);
            }
            try {
                const f = await measure(page, route);
                findings.push(f);
            } catch (e) {
                findings.push({
                    route: route.path,
                    name: route.name,
                    priority: route.priority,
                    docOverflow: {},
                    childOverflow: [],
                    axe: [],
                    error: String(e).slice(0, 200),
                });
            }
            expect(true).toBe(true);
        });
    }

    test.afterAll(async () => {
        const dir = resolve(process.cwd(), 'specs/016-frontend-ux-audit');
        mkdirSync(dir, { recursive: true });
        writeFileSync(resolve(dir, 'audit-findings.json'), JSON.stringify(findings, null, 2));
        // eslint-disable-next-line no-console
        console.log(`\n[sweep] ${findings.length} rotas medidas → audit-findings.json`);
    });
});

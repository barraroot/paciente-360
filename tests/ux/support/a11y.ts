import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';

/**
 * Runner de acessibilidade (feature 016 — gates G11/G12) via axe-core.
 *
 * Roda o axe na página/escopo atual e falha se houver violações de impacto
 * `serious`/`critical` (contraste, rótulos, roles, nome acessível). Tags WCAG
 * 2.1 A/AA conforme a meta do projeto.
 */
export async function runAxe(page: Page, selector?: string) {
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
    if (selector) { builder = builder.include(selector); }
    return builder.analyze();
}

/** Falha o teste se houver violações serious/critical; retorna as violações para log. */
export async function assertNoSeriousA11yViolations(page: Page, selector?: string) {
    const results = await runAxe(page, selector);
    const blocking = results.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
    );
    const summary = blocking.map((v) => `${v.id} (${v.impact}) — ${v.nodes.length} nó(s)`);
    expect(blocking, `violações a11y serious/critical:\n${summary.join('\n')}`).toEqual([]);
    return results.violations; // demais (minor/moderate) ficam como aviso no relatório
}

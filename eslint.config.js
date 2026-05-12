import noUnsanitized from 'eslint-plugin-no-unsanitized';

/**
 * ESLint flat config (ESM) — Paciente360
 *
 * Fase 4 gates obrigatórios (NC-3 / R1 — XSS mitigation):
 *  - no-unsanitized/method: bloqueia innerHTML via .innerHTML = variavel
 *  - no-unsanitized/property: bloqueia outerHTML, insertAdjacentHTML sem sanitização
 *  - vue/no-v-html: avisa sobre v-html sem DOMPurify wrapper
 *
 * DOMPurify deve ser aplicado em TODO HTML user-provided antes de render.
 * Veja: resources/js/lib/sanitize.js (a ser criado em Lote E — US2).
 */
export default [
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/build/**',
            'public/vendor/**',
            'bootstrap/cache/**',
            'storage/**',
        ],
    },
    {
        plugins: {
            'no-unsanitized': noUnsanitized,
        },
        rules: {
            // Bloqueia uso direto de innerHTML/outerHTML/insertAdjacentHTML
            // sem sanitização prévia (XSS gate — Princípio VII + NC-3).
            'no-unsanitized/method': 'error',
            'no-unsanitized/property': 'error',

            // Avisa sobre v-html em componentes Vue — exige DOMPurify wrapper.
            // Mantido como 'warn' (não 'error') para não bloquear casos legítimos
            // que já usam sanitização própria, mas todos os warnings devem ser
            // revisados e suprimidos com comentário justificando a exceção.
            'vue/no-v-html': 'warn',
        },
    },
];

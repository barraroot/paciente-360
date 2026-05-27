#!/usr/bin/env node
/**
 * Varredor de chaves i18n cruas (Gate G14 — feature 016).
 *
 * Compara as chaves referenciadas no código (`t('...')` / `$t('...')`) com as
 * chaves folha presentes em resources/js/i18n/pt-BR.json e reporta as que são
 * referenciadas mas NÃO existem (renderizariam como chave crua na tela).
 *
 * Chaves dinâmicas (template literal com `${...}` ou concatenação) são ignoradas
 * por construção — só casa literais de string estáticos.
 *
 * Uso: node tests/ux/scan-i18n-keys.mjs   (sai 1 se houver chave faltante)
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const ROOT = new URL('../../resources/js', import.meta.url).pathname;
const LOCALE = join(ROOT, 'i18n', 'pt-BR.json');

function flatten(obj, prefix = '', out = new Set()) {
    for (const [k, v] of Object.entries(obj)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v && typeof v === 'object') { flatten(v, key, out); }
        else { out.add(key); }
    }
    return out;
}

function walk(dir, files = []) {
    for (const name of readdirSync(dir)) {
        const p = join(dir, name);
        const s = statSync(p);
        if (s.isDirectory()) { walk(p, files); }
        else if (['.vue', '.js', '.ts'].includes(extname(p))) { files.push(p); }
    }
    return files;
}

const known = flatten(JSON.parse(readFileSync(LOCALE, 'utf8')));
const KEY_RE = /(?:\$t|[^A-Za-z0-9_.$]t)\(\s*['"]([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+)['"]/g;

const missing = new Map(); // key -> [files]
for (const file of walk(ROOT)) {
    const src = readFileSync(file, 'utf8');
    let m;
    while ((m = KEY_RE.exec(src)) !== null) {
        const key = m[1];
        if (!known.has(key)) {
            const rel = file.replace(ROOT + '/', '');
            if (!missing.has(key)) { missing.set(key, new Set()); }
            missing.get(key).add(rel);
        }
    }
}

if (missing.size === 0) {
    console.log(`[i18n] OK — nenhuma chave crua. (${known.size} chaves no pt-BR.json)`);
    process.exit(0);
}

console.log(`[i18n] ${missing.size} chave(s) referenciada(s) e AUSENTE(s) no pt-BR.json:`);
for (const [key, files] of [...missing].sort()) {
    console.log(`  - ${key}  (${[...files].join(', ')})`);
}
process.exit(1);

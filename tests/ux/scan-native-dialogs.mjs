#!/usr/bin/env node
/**
 * Varredor de diálogos nativos (Gate G13 — feature 016).
 *
 * Detecta usos de window.confirm/prompt/alert na SPA, que MUST ser substituídos
 * por modais acessíveis. Ignora ocorrências em comentários de linha simples.
 *
 * Uso: node tests/ux/scan-native-dialogs.mjs   (sai 1 se encontrar)
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const ROOT = new URL('../../resources/js', import.meta.url).pathname;
// Chamada de diálogo nativo (não declaração de método homônimo).
const CALL_RE = /(?<![.\w])(?:window\.)?(confirm|prompt|alert)\s*\(/;
const DECL_RE = /\b(?:function|async)\s+(confirm|prompt|alert)\b|(?:^|[,{]\s*)(confirm|prompt|alert)\s*\(\s*\)\s*\{/;

function walk(dir, files = []) {
    for (const name of readdirSync(dir)) {
        const p = join(dir, name);
        statSync(p).isDirectory() ? walk(p, files)
            : ['.vue', '.js', '.ts'].includes(extname(p)) && files.push(p);
    }
    return files;
}

/** Remove comentários de bloco /* *​/ e de linha // para evitar falsos positivos. */
function stripComments(src) {
    return src.replace(/\/\*[\s\S]*?\*\//g, (b) => b.replace(/[^\n]/g, ' '));
}

const hits = [];
for (const file of walk(ROOT)) {
    const lines = stripComments(readFileSync(file, 'utf8')).split('\n');
    lines.forEach((rawLine, i) => {
        const code = rawLine.replace(/\/\/.*$/, '');
        if (CALL_RE.test(code) && !DECL_RE.test(code)) {
            hits.push(`${file.replace(ROOT + '/', '')}:${i + 1}  ${code.trim().slice(0, 80)}`);
        }
    });
}

if (hits.length === 0) {
    console.log('[dialogs] OK — nenhum confirm/prompt/alert nativo na SPA.');
    process.exit(0);
}
console.log(`[dialogs] ${hits.length} diálogo(s) nativo(s) encontrado(s):`);
hits.forEach((h) => console.log(`  - ${h}`));
process.exit(1);

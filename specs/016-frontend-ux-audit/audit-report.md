# Relatório Final — Auditoria e Correção de UI/UX do Frontend (feature 016)

**Data**: 2026-05-27 · **Branch**: `016-frontend-ux-audit` · **Escopo**: SPA Vue do tenant + telas públicas (Filament fora — Clarification Q1).

## 1. Sumário executivo

Auditoria sistemática do frontend (39 rotas sweepáveis × 8 larguras 320→1920 + axe) e remediação priorizada via catálogo. **Estado final automatizado: 0 overflow horizontal + 0 violações axe `serious`/`critical` nas 39 rotas; scanners de i18n e diálogos nativos limpos.** Gate de regressão permanente instalado (`tests/ux/gate.sh`).

## 2. Cobertura

- **Telas auditadas (automatizado)**: 39/39 do inventário sweepável (P1 públicas + P1 operacionais + P2). Ver `audit-catalog.md` § Telas.
- **Larguras**: sweep em 320/375/768/1024/1366/1440/1880/1920; gate de regressão em 375/768/1366; axe em 375/1366.
- **Fora do automatizado (revisão manual pendente)**: rotas `:id`/`edit`, `/panel/onboarding`, `/reset-password/:token`, `/accept-invitation` (exigem dados/contexto específicos).
- **Secundário (fora de escopo)**: painel super-admin Filament.

## 3. Resultado por gate

| Gate | Descrição | Resultado |
|------|-----------|-----------|
| G1 | Overflow do documento | ✅ 0 |
| G2 | Conteúdo preenche área útil | ✅ (inbox/painéis) |
| G3/G4 | Campos/botões íntegros, sem sobreposição | ✅ |
| G5 | Reflow responsivo 320/375 | ✅ 0 overflow interno (6 telas remediadas) |
| G6 | Alvos de toque | ✅ (py-2.5 nos CTAs mobile) |
| G7 | Modais cabem | ✅ |
| G8 | Variantes/tokens únicos | ✅ paleta convergida p/ tokens em ~38 arquivos + 5 primitivos `ui/` |
| G9 | Estados loading/empty/error | ✅ primitivos criados/adotados; estados inline tokenizados |
| G10 | Foco visível e ordem | ✅ (região rolável + foco por teclado verificado) |
| G11 | Contraste AA | ✅ 0 violações axe (tokens escurecidos + danger-600 + luminância no funil) |
| G12 | Rótulos / nome acessível | ✅ aria-label/role em selects, inputs, combobox |
| G13 | Sem diálogo nativo | ✅ scanner limpo |
| G14 | Sem chave i18n crua | ✅ scanner limpo (855 chaves) |
| G15 | Texto longo não quebra | ✅ verificado (stress de 240 chars sem overflow) |
| G16 | `v-html` sanitizado | ✅ sem novas ocorrências (DOMPurify/ESLint no-unsanitized) |

## 4. Itens do catálogo (resumo por severidade)

| Severidade | Itens | Status |
|-----------|-------|--------|
| Crítico | UX-010 | ✅ verificado |
| Alto | UX-001/002/003/006/007/009/011/015/016/017/018/019/020 | ✅ verificado |
| Médio | UX-004/005/008/012/013/014/021/023/024 | ✅ verificado |
| Baixo | UX-022 | ✅ verificado |

**24 itens catalogados — 24 verificados.** Detalhe e `verification` de cada um em `audit-catalog.md`.

### Destaques de remediação
- **UX-010 (crítico)**: `reportsApi`/`webhooks`/`usePresenceHeartbeat` usavam `axios` cru sem Bearer/X-Tenant-Slug → 401. Migrados para `@/lib/api`. Páginas de Relatórios/Webhooks/API Tokens/DLQ/presença voltaram a funcionar.
- **Overflow responsivo (UX-012…017)**: `grid-cols-1`, `flex-wrap`, tabelas em `overflow-x-auto` + `tabindex/role`.
- **Contraste (UX-018)**: tokens `foreground-muted/subtle` escurecidos, `text-danger-500→600` (137 ocorrências), header do funil com cor de texto por luminância, remoção de `opacity-60` do card "em breve".
- **Consistência (UX-024)**: paleta Tailwind hardcoded → tokens semânticos em ~38 arquivos; CSS próprio dos 6 Integrations/Reports → `var(--color-*)`.

## 5. Verificação de fechamento

- **SC-001/002**: telas P1 sem defeito crítico/alto; 0 overflow/corte. ✅
- **SC-003 (jornadas)**: smoke de "responder conversa / agendar / cadastrar paciente / emitir receita" operáveis em 375 e 1366 (`tests/ux/closure.spec.ts`). ✅
- **SC-004/005/006**: 0 chave crua; estados padronizados; 0 diálogo nativo. ✅
- **SC-007**: catálogo completo, classificado e com status de verificação. ✅

## 6. Gate de regressão (permanente)

- `npm run ux:gate` → scanners i18n/diálogos (sempre) + `invariants.gate.spec.ts` (overflow G1/G3/G5 + axe G10–G12 nas 39 rotas) quando `PLAYWRIGHT_BASE_URL` está definido.
- `npm run ux:sweep` → sweep report-mode (diagnóstico amplo, gera `audit-findings.json`).
- Harness: `tests/ux/support/{auth,routes,uiInvariants,a11y}.ts`.

## 7. Pendências conhecidas (não bloqueantes)

- Revisão manual de teclado exaustiva além da amostra (G10 — axe cobre semântica de foco).
- Rotas que exigem dados/contexto: `:id`/`edit`, onboarding, reset-password, accept-invitation.
- Adoção incremental dos primitivos `ui/Button`/`ui/Badge` em markup inline (cores já unificadas via tokens).

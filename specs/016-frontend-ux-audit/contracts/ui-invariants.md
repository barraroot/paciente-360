# UI Contract — Invariantes Verificáveis

Este é o "contrato" da feature: invariantes que **toda tela auditada MUST satisfazer** nos breakpoints alvo. Cada invariante é um gate de aceitação (G), com método de verificação. São a base das asserções Playwright e dos critérios de `status: verificado` no catálogo.

Faixa de suporte: **contínua de ~320px a ≥1920px** (fluida, sem quebra em nenhuma largura — Clarification Q4). Larguras amostradas na verificação: **320 / 375 / 768 / 1024 / 1366 / 1440 / 1880 / 1920** (amostragem, não recorte da exigência).

## Gates de Layout (desktop e responsivo)

- **G1 — Sem overflow horizontal indevido**: para cada tela/breakpoint, `document.documentElement.scrollWidth <= innerWidth` (tolerância 1px). Nenhum container interno relevante com `scrollWidth > clientWidth` não intencional.
- **G2 — Conteúdo preenche a área útil**: a região de conteúdo principal alcança a borda direita e o rodapé disponíveis (sem espaços mortos), e não ultrapassa a viewport. Ex. inbox: painel de conversa `fillsRight` e `fillsBottom`.
- **G3 — Campos e botões íntegros**: nenhum campo/botão cortado, sobreposto ou fora do container; rótulos e ícones totalmente visíveis. Verificável por `getBoundingClientRect` dentro dos limites do pai + inspeção visual.
- **G4 — Sem sobreposição de controles**: elementos interativos não se cobrem (ex.: ação não cobre badge — caso já corrigido em Canais).

## Gates Responsivos

- **G5 — Reflow em telas pequenas**: em 375/768, layouts multi-painel colapsam para um painel por vez, com navegação clara de ida/volta; nenhum conteúdo essencial inacessível.
- **G6 — Alvos de toque**: em 375/768, controles interativos têm área mínima ~44×44px e não ficam colados a ponto de toque ambíguo.
- **G7 — Modais cabem na tela**: em qualquer breakpoint, modais não estouram a viewport; conteúdo rola internamente se necessário.

## Gates de Consistência (US3)

- **G8 — Variantes únicas**: componentes equivalentes (botão primário, input, badge, card) usam a mesma variante/tokens entre telas (conforme Component Standard).
- **G9 — Estados padronizados**: toda tela com dados assíncronos exibe estados `loading`, `empty` e `error` no padrão único.

## Gates de Acessibilidade (US4)

- **G10 — Foco visível e ordem lógica**: navegação por teclado mostra foco em todos os interativos, em ordem coerente.
- **G11 — Contraste AA**: texto/ícones atendem ao mínimo WCAG 2.1 AA (axe sem violações de contraste).
- **G12 — Rótulos**: todo controle tem nome acessível (`aria-label`/label/texto).
- **G13 — Sem diálogo nativo**: zero `confirm()`/`prompt()`/`alert()`; confirmações destrutivas via modal acessível (`role=alertdialog` + focus-trap + Esc/overlay).

## Gates de i18n (FR-010)

- **G14 — Sem chave crua**: nenhuma string visível corresponde a uma chave i18n não resolvida (ex.: `inbox.ai_pause.release`).
- **G15 — Texto longo não quebra**: nomes/labels/mensagens longos truncam ou refluem sem estourar o container.

## Gates de Segurança de Render (Constituição VII)

- **G16 — `v-html` sanitizado**: todo `v-html`/HTML user-provided passa por DOMPurify; ESLint `no-unsanitized` sem violação.

## Método de verificação (por gate)

| Gate | Método |
|------|--------|
| G1, G2, G3, G4, G7 | Playwright geometria (`getBoundingClientRect`, `scrollWidth/clientWidth`) + screenshot |
| G5, G6 | Playwright em 375/768 (resize) + medição de alvo |
| G8, G9, G15 | Revisão visual comparativa + asserção pontual quando viável |
| G10, G11, G12 | axe-core + navegação por teclado |
| G13, G16 | Grep estático (`confirm(`/`prompt(`/`alert(`, `v-html`) + ESLint |
| G14 | Varredura de chaves `t()` vs `pt-BR.json` + regex em texto renderizado |

**Definição de "verificado"**: um item do catálogo passa a `verificado` quando o(s) gate(s) associado(s) passam no(s) breakpoint(s) afetado(s), com asserção automatizada quando o gate é automatizável (G1–G7, G10–G14, G16) ou evidência visual documentada quando manual (G8, G9, G15).

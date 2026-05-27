# Component Standard — "design de fato" (feature 016 · T006)

Padrão canônico **extraído das telas de melhor qualidade já existentes** (Pacientes, Agenda, modais a11y das Fases 6/12 e `components/ui/ConfirmModal.vue`). Fonte de verdade para os gates **G8 (variantes únicas)** e **G9 (estados padronizados)**. **Não é redesenho**: convergir os divergentes para estas variantes; não reescrever os que já seguem.

Referências de aparência: `resources/css/app.css` (`@theme`). Cores em `oklch`; **sempre via tokens** (`primary-*`, `surface*`, `foreground*`, `border*`, `danger-*`, `warning-*`, `success-*`, `info-*`, `accent-*`), nunca hex cru. Raios `--radius-sm/md/lg/xl`; sombras `--shadow-card`/`--shadow-popover`.

## Tokens (resumo)

| Grupo | Tokens | Uso |
|-------|--------|-----|
| Marca | `primary-50…950` | ações primárias, foco, links |
| Superfície | `surface`, `surface-muted`, `surface-subtle`, `surface-elevated` | fundos / cartões / hover |
| Texto | `foreground`, `foreground-muted`, `foreground-subtle` | hierarquia textual |
| Borda | `border`, `border-strong` | divisores, inputs, cartões |
| Semântico | `danger-50…800`, `warning-50…800`, `success-50…800`, `info-50/500`, `accent-50/500` | estados/feedback (escalas completas após `fix(016)` — UX-011) |
| Forma | `radius-sm/md/lg/xl`, `shadow-card`, `shadow-popover` | raios e elevação |

## Variantes de componente

### button
- **primary**: `rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-60`
- **secondary (outline)**: `rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500`
- **danger (outline)**: `rounded-lg border border-danger-500 px-3 py-1.5 text-sm font-medium text-danger-500 transition hover:bg-danger-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-500` — destrutivo SEMPRE precedido de `ConfirmModal`/`alertdialog`.
- **ghost / link**: `rounded px-2 py-1 text-xs font-medium text-primary-600 transition hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500`
- **full-width** (formulários auth/onboarding): prefixar `w-full` + `py-2.5`.
- Estados: `hover:` definido; foco SEMPRE `focus-visible:outline-2` em `primary-500` (ou `danger-500` no danger); `disabled:opacity-60 disabled:cursor-not-allowed`. Alvo mínimo ~44px (G6) — usar `py-2.5` em mobile quando o alvo ficar curto.

### input / select / textarea
- Base: `w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200`
- Erro: borda `border-danger-500` + mensagem em `text-danger-600`.
- Label: `text-sm font-medium text-foreground`; `<label for>` ou `aria-label` obrigatório (G12).

### badge
- Base: `inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium` (variante "grande": `px-3 py-1 text-sm font-semibold`).
- Cor por status via par tonal semântico: fundo `*-50/100`, texto `*-700/800`, borda opcional `*-200` (ex.: `bg-success-100 text-success-800`).

### card
- `rounded-xl border border-border bg-surface-elevated shadow-card` + padding `p-4`/`p-6`.

### modal / alertdialog
- Padrão `ConfirmModal.vue` + Fases 6/12: `Teleport to="body"` + overlay `fixed inset-0 z-50 bg-black/50 flex items-center justify-center` + painel `bg-surface-elevated rounded-xl shadow-xl max-w-md w-full mx-4 p-6 border border-border`.
- A11y: `role="dialog"` (ou `role="alertdialog"` p/ destrutivo) + `aria-modal="true"` + `aria-labelledby`/`aria-describedby` + focus-trap (`useShellFocusTrap`) + Esc/overlay fecham + foco retorna ao trigger.
- **G7**: em telas pequenas o painel rola internamente (`max-h-[90vh] overflow-y-auto`) — NÃO estoura a viewport.
- **Proibido**: `confirm()`/`prompt()`/`alert()` nativos (G13).

### empty_state
- Centralizado: ícone `foreground-subtle` + título `text-sm font-medium text-foreground` + descrição `text-sm text-foreground-muted` + (opcional) CTA primário. Container `rounded-xl border border-dashed border-border p-8 text-center`.

### loading_state
- Skeleton: blocos `animate-pulse rounded bg-surface-muted` reproduzindo o layout (ref.: `ShellSkeleton.vue`, `Reports/DashboardSkeleton.vue`). Nunca tela em branco nem spinner solto sem contexto.

### error_state
- Bloco: `rounded-lg border border-danger-500 bg-danger-50 px-4 py-3 text-sm text-danger-600` com mensagem acionável + (quando aplicável) botão "Tentar novamente" (secondary). Sem PII clínica na mensagem.

### toast
- Feedback efêmero local (sem lib), padrão Fase 6 — substitui `alert()`. Sucesso/erro/info com par tonal semântico; auto-dismiss + dispensável por teclado.

## Regras de a11y transversais (G10–G13)
- Foco visível em TODO interativo (`focus-visible:outline-2`); ordem de tabulação lógica.
- Contraste mínimo WCAG AA (axe sem violações `serious`/`critical`).
- Todo controle com nome acessível (texto, `<label>` ou `aria-label`).
- Datas/moeda via `Intl`/Luxon pt-BR; nenhuma string hardcoded fora do i18n (G14).
- `v-html` só com DOMPurify (G16).

> **Telas de referência**: `pages/pacientes/PacientesListPage.vue`, `pages/agenda/AgendaPage.vue`, `components/ui/ConfirmModal.vue`. Divergências encontradas no sweep entram no catálogo como `category: consistencia` (US3).

# Data Model — Auditoria e Correção de UI/UX do Frontend

Esta feature **não cria persistência de aplicação**. As "entidades" abaixo descrevem os **artefatos versionados** da auditoria (markdown/JSON no diretório da feature). Servem de fonte única para `/speckit-tasks` e para rastrear remediação/regressão.

## Entidade 1 — Item do Catálogo de Problemas (UX Issue)

Representa um problema de UI/UX encontrado em uma tela. Persistido como linha de um catálogo (`audit-catalog.md` ou `.json`, criado na implementação).

| Campo | Tipo | Regras / Valores |
|-------|------|------------------|
| `id` | string | Identificador estável (ex.: `UX-001`). |
| `screen` | ref → Tela/Rota | Rota auditada (ex.: `/panel/inbox`). |
| `breakpoint` | enum | `375` \| `768` \| `1366` \| `1880` \| `todos`. |
| `scope` | enum | `desktop` \| `responsivo` \| `ambos`. |
| `category` | enum | `layout` \| `overflow` \| `consistencia` \| `a11y` \| `i18n` \| `estado` (loading/empty/error) \| `feedback`. |
| `severity` | enum | `critico` \| `alto` \| `medio` \| `baixo`. |
| `description` | texto | O que está errado (objetivo, sem PII). |
| `recommendation` | texto | Correção proposta. |
| `verification` | texto | Critério objetivo de verificação (ex.: "sem overflow horizontal em 1880; bolha não cortada"). |
| `status` | enum | `aberto` \| `corrigido` \| `verificado`. |
| `test_ref` | string \| null | Referência ao teste Playwright que cobre o invariante (quando aplicável). |

**Regras**:
- Severidade `critico`/`alto` MUST bloquear o fechamento da US correspondente até `verificado` (SC-001).
- Todo item com `category` em {`layout`,`overflow`,`i18n`,`a11y`} MUST ter `verification` automatizável ou justificativa de verificação manual.
- Transições de `status`: `aberto → corrigido → verificado` (sem pular para `verificado`).

## Entidade 2 — Tela / Rota (Auditable Screen)

Unidade auditável derivada de `navigation.js` + `router/index.js`.

| Campo | Tipo | Regras |
|-------|------|--------|
| `route` | string | Caminho (ex.: `/panel/agenda`). |
| `name` | string | Nome da rota / título. |
| `roles` | lista | Papéis que enxergam a tela (controla quais personas testar). |
| `priority` | enum | `P1` \| `P2` \| `secundario` (ver research R8). |
| `states` | lista | Estados a verificar: `loading`,`empty`,`error`,`few`,`many`,`long_text`. |
| `audited_at` | data \| null | Quando a varredura foi concluída. |

## Entidade 3 — Padrão de Componente (Component Standard)

Definição canônica de um tipo de elemento, extraída das telas de referência (R1/R5).

| Campo | Tipo | Regras |
|-------|------|--------|
| `component` | enum | `button` \| `input` \| `select` \| `badge` \| `card` \| `modal` \| `empty_state` \| `loading_state` \| `error_state` \| `toast`. |
| `variants` | lista | Variações suportadas (ex.: botão: `primary`,`secondary`,`danger`,`ghost`). |
| `states` | lista | `default`,`hover`,`focus`,`disabled`,`loading` conforme aplicável. |
| `tokens` | objeto | Tokens de aparência (cor/espacamento/raio/tipografia) referenciados às variáveis CSS existentes. |
| `reference_screen` | ref | Tela de onde o padrão foi extraído. |
| `a11y_rules` | lista | Foco visível, rótulo, contraste AA, `role`/`aria-*` quando aplicável; modais sem diálogo nativo. |

**Relações**:
- `UX Issue.screen` → `Tela/Rota.route` (N:1).
- `UX Issue` pode referenciar um `Component Standard` violado (consistência).
- `Component Standard.reference_screen` → `Tela/Rota` (N:1).

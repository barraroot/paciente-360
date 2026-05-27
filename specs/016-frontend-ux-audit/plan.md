# Implementation Plan: Auditoria e Correção de UI/UX do Frontend

**Branch**: `016-frontend-ux-audit` | **Date**: 2026-05-26 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/016-frontend-ux-audit/spec.md`

## Summary

Auditar sistematicamente o frontend (SPA Vue 3 do tenant + telas públicas) para encontrar campos/botões fora do layout e quebras de usabilidade — desktop e responsivo — e remediar de forma priorizada, convergindo todas as telas para um padrão visual consistente, acessível e fácil de usar.

Abordagem técnica: a referência de design é o **padrão de fato** extraído das telas de melhor qualidade já existentes (FR-013); o suporte é **fluido contínuo de ~320px a ≥1920px** (FR-014, Clarification Q4), amostrado em larguras representativas (320/375/768/1024/1366/1440/1880/1920). A auditoria combina checagens **automatizadas de geometria/acessibilidade** (Playwright para overflow/preenchimento/alvos de toque + axe para a11y + varredura de chaves i18n cruas) com revisão visual manual. Cada problema vira item de um **catálogo priorizado**; cada correção carrega um **critério de verificação objetivo** com asserção automatizada quando viável, para prevenir regressão. Nenhuma mudança de backend, dados, IA ou canais.

## Technical Context

**Language/Version**: JavaScript/Vue 3 (Composition API), PHP 8.5 apenas onde houver string de UI server-side (mensagens). Tooling via `vendor/bin/sail`.
**Primary Dependencies**: Vue 3 + Pinia + Vue Router + Vue I18n; Tailwind CSS v4; Laravel Echo/Reverb (já existente); Playwright (verificação E2E/geometria); axe-core (a11y).
**Storage**: N/A — o catálogo de problemas é um artefato versionado (markdown/JSON em `specs/016-frontend-ux-audit/`), não persistência de aplicação.
**Testing**: Playwright (E2E + asserções de invariantes de layout/a11y), PHPUnit apenas se alguma string de UI server-side mudar. Pint para PHP eventualmente tocado; ESLint/Prettier no front.
**Target Platform**: Navegadores modernos (SPA), responsivo fluido de ~320px a ≥1920px.
**Project Type**: Web app (SPA Vue + API Laravel) — esta feature toca **somente o frontend** e arquivos de i18n.
**Performance Goals**: Sem regressão de tempo de render; sem reflow/jank perceptível ao abrir telas e modais.
**Constraints**: Reuso da identidade visual atual (não é redesenho); zero overflow horizontal indevido; contraste mínimo AA; sem diálogos nativos; sem chaves i18n cruas.
**Scale/Scope**: ~65 páginas Vue + componentes compartilhados; foco P1 nas telas operacionais centrais (Inbox/Conversas, Agenda, Pacientes, Receituários, Canais, Dashboard) e públicas (login, cadastro, recuperação, onboarding). Painel super-admin Filament é escopo secundário (app server-rendered separado, convenções próprias).

## Constitution Check

*GATE: avaliado contra os 7 princípios (v1.5.0). Esta é uma feature corretiva de UI, sem novas funcionalidades de negócio.*

| Princípio | Aplicável? | Veredito | Observação |
|-----------|-----------|----------|------------|
| I. LGPD (NN) | Parcial | **PASS** | Não adiciona fluxo de dados. A auditoria MUST **preservar** salvaguardas existentes (mascaramento de controladas, aviso LGPD de mídia, pseudonimização) e não expor PII nova na UI. |
| II. Isolamento Multi-Tenant (NN) | Não | **PASS** | Sem mudança em persistência/fila/broadcast. Layout não cruza tenant. |
| III. Segurança Clínica IA (NN) | Não | **PASS** | Sem alteração na camada de IA. |
| IV. Spec-Driven Test-First | Sim | **PASS (com compromisso)** | Correções de comportamento observável MUST ter asserção de regressão (Playwright) onde viável — invariantes de layout/a11y/i18n. Desvio consciente: defeitos puramente estéticos sem comportamento testável ficam com verificação manual documentada (precedente das fases anteriores). |
| V. Observabilidade | Não | **PASS** | Sem caminho de servidor novo. |
| VI. Conformidade Meta (NN) | Não | **PASS** | Sem disparo de canal. |
| VII. Segurança Operacional (NN) | Parcial | **PASS** | A auditoria MUST sinalizar qualquer `v-html` sem DOMPurify e preservar CSP. Sem nova superfície de auth. |
| Localização e Idioma | Sim | **PASS / reforçado** | FR-010 — varredura de chaves i18n cruas e textos longos faz parte do escopo; strings vivem em arquivos de tradução. |
| Arquitetura (SPA/camadas) | Sim | **PASS** | Trabalho dentro da estrutura existente (Vue components/pages/stores). Nenhuma tela Blade/Filament de tenant criada. |

**Resultado: PASS — 9/9, sem violação e sem necessidade de amendment.** Sem itens em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/016-frontend-ux-audit/
├── plan.md              # Este arquivo
├── research.md          # Fase 0 — decisões de método, breakpoints, tooling
├── data-model.md        # Fase 1 — schema do catálogo de problemas + entidades
├── quickstart.md        # Fase 1 — como auditar, verificar e remediar
├── contracts/
│   └── ui-invariants.md # Fase 1 — invariantes verificáveis (layout/responsivo/a11y/i18n) + padrões de componente
├── checklists/
│   └── requirements.md  # Checklist de qualidade da spec
└── tasks.md             # Fase 2 — gerado por /speckit-tasks (NÃO criado aqui)
```

### Source Code (repository root)

A feature altera **somente** o frontend e i18n. Diretórios reais tocados:

```text
resources/js/
├── pages/            # ~65 telas a auditar (Inbox/, Agenda/, Pacientes/, Receituarios/, Canais/, Dashboard, etc. + públicas)
├── components/       # componentes compartilhados e por domínio (Inbox/, Canais/, layout/, ui/)
│   ├── ui/           # primitivos candidatos a padronização (botões, inputs, badges, modais, empty/loading/error states)
│   └── layout/       # AppShell, Sidebar, Topbar, Drawer
├── composables/      # useShellFocusTrap, useNavigation, etc. (a11y/responsivo)
├── config/           # navigation.js (fonte de telas/abilities)
└── i18n/
    └── pt-BR.json    # cobertura de tradução (FR-010)

tests/ (Playwright)   # asserções de invariantes de layout/a11y/i18n por tela
```

**Structure Decision**: Single web app existente. Esta feature é corretiva e não introduz novas pastas raiz; opera dentro de `resources/js/**` e `resources/js/i18n/pt-BR.json`, com verificação em `tests/` (Playwright). Painel Filament (`app/Filament/**`) fica como escopo secundário documentado, fora do P1.

## Complexity Tracking

> Sem violações constitucionais. Nada a justificar.

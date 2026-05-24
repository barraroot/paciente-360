# A11y Audit — Gestão de Profissionais (012)

**Data**: 2026-05-24 | **Ferramenta**: axe-core 4.10.2 (via Playwright/Chromium) | **Meta SC-007**: 0 violations sérias/críticas

Auditoria executada no ambiente staging (`https://clinica-alfa.paciente-360.com/panel/profissionais`) como `admin-clinica`, nos viewports **1280px** (desktop) e **360px** (mobile).

## Resultado final — PASS

| Superfície | Viewport | Violations |
|---|---|---|
| Lista de profissionais | 1280px | **0** |
| Lista de profissionais | 360px | **0** |
| Form modal (criar — modo "usuário existente" e "convidar por email") | 1280px | **0** |
| Modal de desativação (`alertdialog`) | 360px | **0** |

Atributos de modal verificados: `role="dialog"`/`role="alertdialog"`, `aria-modal="true"`, `aria-labelledby`/`aria-label`, `aria-describedby` (deactivate), foco preso dentro do modal.

## Violations encontradas e corrigidas nesta auditoria

A primeira passada do axe (antes das correções) reportou:

| Regra | Impacto | Onde | Correção |
|---|---|---|---|
| `label` | crítico | input "Número do conselho" sem `<label>` associado | `for`/`id` em todos os campos do `ProfessionalFormModal` + `CouncilTypeSelect` |
| `select-name` | crítico | selects "Tipo de conselho" e "UF" sem nome acessível | idem (`for`/`id`) |
| `landmark-no-duplicate-banner` / `landmark-unique` | moderado | `<header>` dos modais virava landmark `banner` duplicado da topbar (modais são `Teleport to body`) | `<header>` → `<div>` em `ProfessionalFormModal`, `DeactivateConfirmModal`, `EmailAlreadyUserModal` |

Após as correções + `npm run build`, nova passada do axe retornou **0 violations** em todas as superfícies e viewports.

## Notas

- O `EmailAlreadyUserModal` recebeu o mesmo fix `<header>`→`<div>` dos outros dois modais (idêntico ao validado).
- Badge de status na tabela usa **texto + ícone (●)** além de cor (FR-033) — não depende só de cor.
- Erros de formulário/limite de plano usam `role="alert"`.

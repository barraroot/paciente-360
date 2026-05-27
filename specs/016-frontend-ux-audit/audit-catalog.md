# Catálogo de Problemas de UI/UX — feature 016

Fonte única de rastreamento (data-model.md). Status: `aberto` → `corrigido` → `verificado`.
Severidade: `critico`/`alto`/`medio`/`baixo` · Escopo: `desktop`/`responsivo`/`ambos` · Categoria: `layout`/`overflow`/`consistencia`/`a11y`/`i18n`/`estado`/`feedback`.

> **Seed inicial** (Fase 1): itens detectados pelos scanners automatizados. O sweep manual/Playwright (T008–T010) adicionará os demais.

## Telas (inventário) — preencher em T007

_(a popular a partir de `navigation.js` + `router/index.js`)_

## Itens

| id | screen | category | severity | scope | description | recommendation | verification | status | test_ref |
|----|--------|----------|----------|-------|-------------|----------------|--------------|--------|----------|
| UX-001 | `components/Inbox/AiPauseBadge.vue`,`ReleaseAiButton.vue`,`TakeoverButton.vue` | i18n | alto | ambos | 10 chaves `inbox.ai_pause.*` cruas (badge/release/takeover) | Adicionar bloco `inbox.ai_pause` ao `pt-BR.json` | `scan-i18n-keys.mjs` limpo | **verificado** (merge da branch de fixes) | G14 |
| UX-002 | `pages/pacientes/PacientesListPage.vue` | i18n | alto | ambos | Chave crua `import.export_cta` | Adicionar `import.export_cta` ao `pt-BR.json` | `scan-i18n-keys.mjs` limpo | **verificado** | G14 |
| UX-003 | `pages/prescriptions/PrescriptionsReportPage.vue` | i18n | alto | ambos | Chave crua `prescription.restricted_access` | Adicionar bloco `prescription.restricted_access` ao `pt-BR.json` | `scan-i18n-keys.mjs` limpo | **verificado** | G14 |
| UX-004 | `components/ImpersonateBanner.vue:48` | feedback | medio | ambos | `alert()` nativo em falha de impersonate | Substituir por toast/feedback a11y | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-005 | `components/Inbox/MessageInput.vue:284` | feedback | medio | ambos | `alert()` nativo ("anexar em breve") | Substituir por toast/estado inline | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-006 | `pages/Campaigns/CampaignShowPage.vue:35` | feedback | alto | ambos | `confirm()` nativo no disparo de campanha (ação sensível) | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-007 | `pages/Integrations/ApiTokensSettingsPage.vue:53` | feedback | alto | ambos | `window.confirm()` na revogação de token | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-008 | `pages/Integrations/WebhookDeliveriesPage.vue:17` | feedback | medio | ambos | `window.confirm()` no reenvio de DLQ | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-009 | `pages/Integrations/WebhooksSettingsPage.vue:54` | feedback | alto | ambos | `window.confirm()` na remoção de webhook | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | **verificado** | G13 |
| UX-010 | `pages/Integrations/ApiTokensSettingsPage.vue`,`stores/webhooks.js`,`lib/reportsApi.js`,`composables/usePresenceHeartbeat.js` | layout/estado | **critico** | ambos | Usam `axios` cru (sem Bearer/X-Tenant-Slug) → **401, página não funciona** (regressão cookie→Bearer/Fase 4). Afeta API Tokens, Webhooks, DLQ, Relatórios, presença | Trocar `axios` cru pelo instance `@/lib/api` (dropar prefixo `/api/v1`) | Página carrega/opera sem 401 (smoke) | **ApiTokens verificado**; webhooks/reports/presence **aberto** | — |
| UX-011 | `resources/css/app.css` (tema global) | consistencia | **alto** | ambos | Escalas `danger`/`warning`/`success` incompletas (só 50/500, etc.) → `bg-danger-600`, `text-warning-700`, `border-success-200` etc. **não geram cor** → botões/badges/alertas de perigo/aviso/sucesso sem cor em todo o app (ex.: botão "Revogar" invisível) | Completar as escalas 50–800 no `@theme` | Botão danger visível (oklch 0.53 0.18 25); build OK | **verificado** | G8 |

> Nota de base: a branch `fix/inbox-realtime-layout-e-infra` foi **mesclada** na `016` (decisão B no `/speckit-implement` 2026-05-27), trazendo os fixes de inbox/realtime/i18n `ai_pause`. Por isso UX-001 está `verificado` aqui.

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
| UX-004 | `components/ImpersonateBanner.vue:48` | feedback | medio | ambos | `alert()` nativo em falha de impersonate | Substituir por toast/feedback a11y | `scan-native-dialogs.mjs` limpo | aberto | G13 |
| UX-005 | `components/Inbox/MessageInput.vue:284` | feedback | medio | ambos | `alert()` nativo ("anexar em breve") | Substituir por toast/estado inline | `scan-native-dialogs.mjs` limpo | aberto | G13 |
| UX-006 | `pages/Campaigns/CampaignShowPage.vue:35` | feedback | alto | ambos | `confirm()` nativo no disparo de campanha (ação sensível) | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | aberto | G13 |
| UX-007 | `pages/Integrations/ApiTokensSettingsPage.vue:53` | feedback | alto | ambos | `window.confirm()` na revogação de token | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | aberto | G13 |
| UX-008 | `pages/Integrations/WebhookDeliveriesPage.vue:17` | feedback | medio | ambos | `window.confirm()` no reenvio de DLQ | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | aberto | G13 |
| UX-009 | `pages/Integrations/WebhooksSettingsPage.vue:54` | feedback | alto | ambos | `window.confirm()` na remoção de webhook | Modal a11y de confirmação | `scan-native-dialogs.mjs` limpo | aberto | G13 |

> Nota de base: UX-001 e os fixes de layout/realtime do inbox já existem na branch `fix/inbox-realtime-layout-e-infra` (ainda não mergeada). A branch `016` foi criada a partir da `main` e **não** os contém — ver checkpoint do `/speckit-implement`.

# Quickstart — Auditoria e Correção de UI/UX

Como executar a auditoria, verificar uma tela e remediar com prevenção de regressão. Tudo via `vendor/bin/sail`.

## Pré-requisitos

- App rodando (Sail up) e acessível por subdomínio de tenant (ex.: `https://clinica-alfa.paciente-360.com`).
- Build de frontend atual: `vendor/bin/sail npm run build` (ou `npm run dev`).
- Credenciais de seed para cada persona (DevSeeder, senha `password123`): admin, médico, atendente, recepcionista.

## 1. Auditar uma tela (por breakpoint)

Para cada rota do inventário (research R8), nas larguras amostradas (320/375/768/1024/1366/1440/1880/1920) e arrastando a janela para flagrar quebras entre elas (faixa contínua ~320→≥1920px, Clarification Q4):

1. Logar com a persona apropriada e abrir a rota.
2. **Geometria** (Playwright): medir overflow horizontal (G1), preenchimento da área principal (G2), integridade de campos/botões (G3/G4), modais (G7). Em 375/768 medir reflow (G5) e alvos de toque (G6).
3. **Acessibilidade**: rodar axe (G11/G12), navegar por teclado (G10).
4. **i18n**: conferir ausência de chave crua (G14) e texto longo (G15).
5. **Estados**: forçar loading/empty/error/poucos/muitos/texto-longo e conferir G9.
6. Registrar cada defeito no **catálogo** (`audit-catalog.md`) conforme `data-model.md`.

> O catálogo é o entregável da US5 e a entrada para `/speckit-tasks`.

## 2. Critérios de aceite (gates)

Os invariantes a satisfazer estão em [`contracts/ui-invariants.md`](./contracts/ui-invariants.md) (G1–G16). Uma tela está "limpa" quando não há item de severidade `critico`/`alto` aberto e os gates aplicáveis passam nos breakpoints afetados.

## 3. Remediar com prevenção de regressão

Para cada item:

1. Corrigir no componente/tela (reusar o padrão de referência — não reinventar).
2. `vendor/bin/sail npm run build`.
3. Verificar ao vivo (Playwright) nos breakpoints afetados.
4. Quando o gate é automatizável, adicionar/atualizar asserção Playwright (`test_ref` no catálogo) — ex.: sem overflow, `fillsRight/fillsBottom`, sem chave crua, foco visível.
5. Atualizar `status` do item: `corrigido` → `verificado`.
6. Se tocar PHP (string server-side): `vendor/bin/sail bin pint --dirty --format agent`.

## 4. Padrões de referência (extraídos das telas boas)

Antes de criar/alterar componente, conferir o **Component Standard** (`data-model.md` Entidade 3) e reusar:
- Modal a11y: `Teleport` + `role=dialog/alertdialog` + `useShellFocusTrap` + Esc/overlay (Fases 6/12).
- Datas/moeda: `Intl`/Luxon (pt-BR).
- Cores/sombras/raios: variáveis CSS existentes (`surface`, `border`, `foreground*`, `--shadow-card`).
- **Proibido**: `confirm()`/`prompt()`/`alert()` nativos; `v-html` sem DOMPurify; string hardcoded fora do i18n.

## 5. Verificação final da feature

- SC-001/SC-002: telas P1 sem defeito crítico/alto; zero overflow/corte nos breakpoints alvo.
- SC-003: tarefas-chave (responder conversa, agendar, cadastrar paciente, emitir receita) concluídas sem obstrução em desktop e mobile (teste de usabilidade ≥95%).
- SC-004/005/006: zero chave crua; estados padronizados; zero diálogo nativo.
- SC-007: catálogo completo, classificado e com status de verificação.

## Notas

- Painel super-admin Filament é escopo **secundário** (app server-rendered separado).
- Esta feature **não** altera backend/dados/IA/canais; PRs que extrapolarem isso saem de escopo.

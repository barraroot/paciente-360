# Contract — Navigation Tree

**Status**: Complete | **Date**: 2026-05-23

Esta feature não expõe APIs HTTP novas. O "contrato" relevante é a **estrutura estática da árvore de navegação** que o shell consome e a interface entre o `useNavigation()` composable e a sidebar. Definido aqui para servir como referência canônica e gate de teste E2E.

---

## 1. Tipos de entrada

### `NavGroup`

```typescript
interface NavGroup {
  key: string;                  // ID único do grupo, ex.: 'agenda'
  labelKey: string;             // i18n key, ex.: 'layout.sidebar.agenda.title'
  icon: Component;              // Componente SVG inline (Heroicons)
  children: NavItem[];          // Sub-items
}
```

### `NavItem`

```typescript
interface NavItem {
  key: string;                  // ID único do item, ex.: 'agenda.calendar'
  labelKey: string;             // i18n key, ex.: 'layout.sidebar.agenda.calendar'
  icon?: Component;             // Opcional em sub-items (grupos têm ícone visível)
  routeName: string;            // Nome de rota Vue Router, ex.: 'agenda.index'
  ability?: string;             // Permission requerida (Spatie name)
  anyOf?: string[];             // OR — visível se o usuário tem QUALQUER uma
}
```

### `NavRoot`

```typescript
type NavRoot = Array<NavGroup | NavItem>;
```

Mistura grupos e items soltos no nível raiz (ex.: "Dashboard" e "Receituários" são items diretos; "Agenda" e "Pacientes" são grupos).

---

## 2. Árvore canônica (definição completa)

Esta é a árvore que `resources/js/config/navigation.js` MUST exportar:

```text
- Dashboard                            (NavItem)  routeName: 'panel.home'                                  ability: (none — visível para qualquer usuário autenticado)
- Agenda                               (NavGroup) ability requerida: derivada dos children
  ├─ Calendário                        (NavItem)  routeName: 'agenda.index'                                ability: 'agenda.view'
  ├─ Lista de espera                   (NavItem)  routeName: 'agenda.waitlist'                             ability: 'agenda.view'
  ├─ Tipos de consulta                 (NavItem)  routeName: 'agenda.types.index'                          ability: 'agenda.manage'
  ├─ Horários                          (NavItem)  routeName: 'agenda.schedule.index'                       ability: 'agenda.manage'
  └─ Sincronização Google              (NavItem)  routeName: 'agenda.sync.index'                           ability: 'agenda.manage'
- Pacientes                            (NavGroup)
  ├─ Lista                             (NavItem)  routeName: 'pacientes.list'                              ability: 'paciente.view'
  ├─ Funil Kanban                      (NavItem)  routeName: 'pacientes.funil.kanban'                      ability: 'paciente.view'
  ├─ Importação                        (NavItem)  routeName: 'pacientes.import.upload'                     ability: 'paciente.import'
  └─ Mesclagem                         (NavItem)  routeName: 'pacientes.mesclagem'                         ability: 'paciente.merge'
- Inbox                                (NavGroup)
  ├─ Conversas                         (NavItem)  routeName: 'inbox.index'                                 ability: 'inbox.view'
  ├─ Canais                            (NavItem)  routeName: 'canais.index'                                ability: 'channel.connect'
  ├─ Regras de atribuição              (NavItem)  routeName: 'inbox.regras_atribuicao'                     ability: 'inbox.assign'
  └─ Respostas rápidas                 (NavItem)  routeName: 'inbox.respostas_rapidas'                     ability: 'inbox.view'
- Receituários                         (NavItem)  routeName: 'prescriptions.index'                         ability: 'prescription.view'
- Campanhas                            (NavItem)  routeName: 'campaigns.index'                             ability: 'campaign.create'
- Relatórios                           (NavGroup)
  ├─ Dashboard Executivo               (NavItem)  routeName: 'reports.executive'                           ability: 'report.view'
  ├─ Operacional                       (NavItem)  routeName: 'reports.operational'                         ability: 'report.view'
  └─ Clínico                           (NavItem)  routeName: 'reports.clinical'                            ability: 'report.view'
- Integrações                          (NavGroup)
  ├─ Webhooks                          (NavItem)  routeName: 'integrations.webhooks'                       ability: 'webhook.manage'
  ├─ Dead Letter Queue                 (NavItem)  routeName: 'integrations.webhooks.dlq'                   ability: 'webhook.manage'
  └─ Tokens API                        (NavItem)  routeName: 'integrations.api_tokens'                     ability: 'api_token.manage'
- Privacidade & LGPD                   (NavGroup)
  ├─ Consentimentos                    (NavItem)  routeName: 'privacy.consents'                            ability: 'privacy.view'
  ├─ Direito ao Esquecimento           (NavItem)  routeName: 'privacy.forgetting'                          ability: 'privacy.view'
  └─ Portabilidade de Dados            (NavItem)  routeName: 'privacy.portability'                         ability: 'privacy.view'
- Configurações                        (NavGroup)
  ├─ Sessões / Tokens                  (NavItem)  routeName: 'auth.tokens'                                 ability: (none — qualquer autenticado)
  ├─ Usuários                          (NavItem)  routeName: 'users.list'                                  ability: 'user.manage'
  ├─ Audit Logs                        (NavItem)  routeName: 'audit.list'                                  ability: 'audit.view'
  ├─ Planos                            (NavItem)  routeName: 'billing.plans'                               ability: 'billing.manage'
  ├─ Assinatura                        (NavItem)  routeName: 'billing.subscription'                        ability: 'billing.manage'
  └─ Uso de IA                         (NavItem)  routeName: 'billing.ai-usage'                            ability: 'billing.view'
```

### Observações importantes sobre permissions

- **Items sem `ability` definida** (Dashboard, Sessões/Tokens) são sempre visíveis para qualquer usuário autenticado — não há gate de permission além da auth.
- **Grupos não declaram `ability` próprio** — derivam: grupo é visível se ≥ 1 children visível para o usuário (filtro em runtime).
- **Permissions usadas aqui MUST existir no `RolesSeeder` ou nas seeders de cada fase**:
  - Fase 5 (agenda): `agenda.view`, `agenda.manage`
  - Fase 2 (pacientes): `paciente.view`, `paciente.import`, `paciente.merge`
  - Fase 3 (inbox): `inbox.view`, `inbox.assign`, `channel.connect`
  - Fase 7 (prescriptions): `prescription.view`
  - Fase 8 (campaigns/reports/privacy/integrations): `campaign.create`, `report.view`, `privacy.view`, `webhook.manage`, `api_token.manage`
  - Fundação: `user.manage`, `audit.view`, `billing.manage`, `billing.view`
- Se uma permission listada aqui não existir no seeder, o teste E2E `AppShellNavigationTest::test_all_nav_abilities_exist_in_seeders` falha — gate de integridade do contrato.

---

## 3. API do composable `useNavigation()`

```typescript
function useNavigation(): {
  // Árvore filtrada por permissions do usuário atual.
  visibleNav: ComputedRef<NavRoot>;

  // True se a árvore filtrada está vazia (usuário sem nenhuma permission de módulo).
  isEmpty: ComputedRef<boolean>;

  // Group key da rota atual (para auto-expand). null se a rota não está em nenhum grupo.
  currentGroupKey: ComputedRef<string | null>;

  // Item key da rota atual (para active highlight). null se a rota não está em nenhum item.
  currentItemKey: ComputedRef<string | null>;
}
```

### Contrato comportamental

- **filtragem**: para cada `NavItem`, item é incluído sse:
  - `item.ability == null && item.anyOf == null` → SEMPRE incluído (autenticado-only).
  - `item.ability != null` → incluído sse `auth.permissions.includes(item.ability)`.
  - `item.anyOf != null` → incluído sse `auth.permissions.some(p => item.anyOf.includes(p))`.
- **grupos vazios são removidos**: para cada `NavGroup`, se após filtrar os children sobrarem zero items, o grupo inteiro é omitido.
- **ordem preservada**: a saída mantém a ordem declarativa do arquivo `config/navigation.js`.
- **reatividade**: `visibleNav` reage automaticamente a mudanças em `auth.permissions` (ex.: após `auth.fetchMe()` atualizar).

---

## 4. API do composable `useShellPreferences()`

```typescript
function useShellPreferences(): {
  sidebarMode: Ref<'expanded' | 'compact'>;
  expandedGroups: Ref<string[]>;
  toggleSidebarMode(): void;
  toggleGroup(groupKey: string): void;
  isGroupExpanded(groupKey: string): boolean;
}
```

### Contrato comportamental

- Default `sidebarMode`: `'expanded'` em viewports ≥ 1024px, `'compact'` em 768–1023px (consultado UMA vez no primeiro mount; subsequente segue preferência salva).
- `toggleSidebarMode`: alterna entre os dois valores e persiste em localStorage.
- `toggleGroup`: adiciona ou remove `groupKey` em `expandedGroups`; persiste; emite reatividade.
- `isGroupExpanded`: retorna `expandedGroups.includes(groupKey) || groupKey === currentGroupKey`. Ou seja, o grupo da rota corrente está SEMPRE expandido visualmente, independente do array salvo (UX: usuário sempre vê o contexto da rota atual).
- Em caso de localStorage indisponível: refs continuam mutáveis em memória mas não persistem. Operações nunca lançam.

---

## 5. Contrato do `meta` de rota

Cada rota declarada em `router/index.js` filha de `/panel` MUST/SHOULD ter:

| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `requiresAuth` | `boolean` | MUST (`true`) | Já existe — guard global de auth. |
| `ability` | `string` | SHOULD | Permission do Spatie requerida. Se ausente, rota é acessível a qualquer autenticado. |
| `title` | `string` ou `(route) => string` | SHOULD | Título contextual exibido na topbar e usado em `document.title`. Default fallback: label do item de nav que aponta para a rota. |

Rotas que não declararem `title` continuam funcionando — fallback para o `labelKey` do item correspondente da nav tree. Mas o ideal é declarar para resolver coerentemente sub-páginas que não estão no menu (ex.: `pacientes.show`, `prescriptions.show`).

---

## 6. Gates de validação

Os seguintes invariantes MUST ser garantidos via testes:

| Gate | Teste | Falha se |
|---|---|---|
| **G1** — Todas as rotas usadas em `navigation.js` existem | `AppShellNavigationTest::test_nav_routes_exist` | `routeName` aponta para rota inexistente. |
| **G2** — Todas as abilities usadas existem nas seeders | `AppShellNavigationTest::test_nav_abilities_exist` | Ability referenciada não está no Spatie. |
| **G3** — Filtro de permission funciona | `AppShellNavigationTest::test_user_without_X_does_not_see_X_item` | Sidebar mostra item para usuário sem a ability. |
| **G4** — Grupos vazios somem | `AppShellNavigationTest::test_empty_group_is_hidden` | Grupo aparece sem children visíveis. |
| **G5** — Ordem preservada | `AppShellNavigationTest::test_nav_order_matches_config` | Items renderizam em ordem diferente do array. |
| **G6** — Isolamento de preferences entre tenants | `AppShellPreferencesTest::test_preferences_isolated_by_tenant` | Preferências de tenant A vazam para tenant B no mesmo navegador. |
| **G7** — Isolamento de preferences entre usuários do mesmo tenant | `AppShellPreferencesTest::test_preferences_isolated_by_user` | Preferências de user A vazam para user B no mesmo tenant. |

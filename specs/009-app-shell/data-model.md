# Data Model — App Shell (009)

**Status**: Complete | **Date**: 2026-05-23

Esta feature **não introduz entidades persistentes em banco de dados**. O único "modelo de dados" relevante é o esquema de preferências de UI armazenado em `localStorage`. Sem migrations, sem alterações em models Eloquent.

---

## 1. Esquema de preferências em `localStorage`

### Chave de armazenamento

```
app-shell:preferences:v1
```

- Prefixo `app-shell:` evita colisão com outras chaves do projeto (auth tokens, etc.).
- Sufixo `:v1` permite versionamento futuro: se o schema mudar de forma incompatível, mudamos para `:v2` e o `useShellPreferences` ignora valores antigos (defaults aplicados).

### Valor (JSON)

```json
{
  "rb-clinic": {
    "1": {
      "sidebarMode": "expanded",
      "expandedGroups": ["agenda", "pacientes"]
    },
    "5": {
      "sidebarMode": "compact",
      "expandedGroups": []
    }
  },
  "clinica-alfa": {
    "1": {
      "sidebarMode": "expanded",
      "expandedGroups": ["relatorios"]
    }
  }
}
```

### Schema (informal)

```
ShellPreferences := {
  [tenantSlug: string]: {
    [userId: string]: UserShellPreferences
  }
}

UserShellPreferences := {
  sidebarMode: 'expanded' | 'compact',
  expandedGroups: string[]  // keys de grupos da árvore de navegação
}
```

### Campos

| Campo | Tipo | Default | Descrição |
|---|---|---|---|
| `sidebarMode` | enum `'expanded' \| 'compact'` | `'expanded'` em desktop (≥ 1024px), `'compact'` em tablet (768–1023px). Em mobile o valor é ignorado (drawer não respeita modo). | Modo visual da sidebar. |
| `expandedGroups` | `string[]` | `[]` (grupos fechados por default; o grupo da rota corrente abre automaticamente independente do array). | Lista de `key`s dos grupos manualmente expandidos pelo usuário. |

### Operações

| Operação | Quando | Comportamento |
|---|---|---|
| **Read** | A cada mount do `AppShell` | Lê chave, navega pelo path `[tenantSlug][userId]`, retorna defaults se ausente. |
| **Write `sidebarMode`** | Toggle do botão colapsar/expandir | Escreve no path `[tenantSlug][userId].sidebarMode` e re-serializa o JSON completo. |
| **Write `expandedGroups`** | Usuário expande ou colapsa um grupo manualmente | Adiciona/remove a `key` do array no path apropriado. Re-serializa. |
| **Delete (cleanup)** | Schema bump (v1 → v2) | Implementação futura ignora `:v1`; cleanup oportunista pode apagar. |

### Regras de invariância

- **INV-1** (isolamento multi-tenant): chave de leitura/escrita **MUST** usar `auth.tenant.slug` + `auth.user.id` do contexto atual. Nunca outro path.
- **INV-2** (tolerância a corrupção): se o JSON estiver malformado (falha em `JSON.parse`), o composable retorna defaults e sobrescreve a chave com valor válido vazio. Sem exceção propagada.
- **INV-3** (tolerância a localStorage indisponível): se `window.localStorage` for inacessível (modo privado/Safari, cota cheia), as operações de read retornam defaults e operações de write são no-op silenciosas. Feature continua usável (sem persistência).
- **INV-4** (size cap): assumindo ~100 tenants × ~10 usuários × ~200 bytes/user = ~200 KB no pior caso. Bem dentro da cota típica de 5–10 MB. Sem necessidade de eviction lógica neste spec.

### Sem PII

Nenhum campo armazenado contém PII (nome, email, CPF, dados clínicos). Apenas IDs internos e enums de UI. Compatível com Princípio I (LGPD minimização) e com a restrição de não usar localStorage para tokens sensíveis (Princípio VII já garante isso para tokens Bearer — não duplicamos).

---

## 2. Estado consumido (read-only) da `useAuthStore`

O shell **não modifica** a store de auth — apenas lê. Campos consumidos:

| Campo | Origem | Uso no shell |
|---|---|---|
| `auth.user.name` | `/auth/me` | Topbar user menu — nome exibido |
| `auth.user.email` | `/auth/me` | Topbar user menu — email exibido |
| `auth.user.id` | `/auth/me` | Chave de localStorage (escopo de preferências) |
| `auth.tenant.name` | `/auth/me` (TenantResource) | Topbar — nome da clínica à esquerda |
| `auth.tenant.slug` | `/auth/me` (TenantResource) | Chave de localStorage + (em handlers) `X-Tenant-Slug` (já feito pelo axios interceptor da Fase 4) |
| `auth.permissions` | `/auth/me` | Filtro de items da sidebar — cada item é exibido apenas se a ability requerida está na lista. |
| `auth.isBooting` | computed na store | Sinal para exibir `ShellSkeleton.vue`. |
| `auth.isAuthenticated` | computed na store | Não usado direto no shell (router guard já barra anônimo). |

---

## 3. Esquema de árvore de navegação (estática, código-fonte)

Não é "data" no sentido persistido — vive no código. Contrato detalhado em [contracts/navigation-tree.md](./contracts/navigation-tree.md).

Resumo da forma:

```
NavGroup := {
  key: string,
  labelKey: string,        // i18n key, ex.: 'layout.sidebar.agenda.title'
  icon: Component,         // Heroicon SVG
  children?: NavItem[]
}

NavItem := {
  key: string,
  labelKey: string,
  icon?: Component,        // opcional em sub-items
  routeName: string,       // ex.: 'agenda.index'
  ability?: string,        // ex.: 'inbox.view'
  anyOf?: string[]         // OR de abilities (alternativa a ability)
}
```

Não há `relacionamentos` no sentido relacional — é uma árvore simples de uma profundidade (grupo → items). Validação:

- Cada `routeName` MUST existir no router (testes E2E falham se quebrar).
- Cada `ability` MUST corresponder a uma permission seedada no banco (já garantido pelo `RolesSeeder`).

---

## 4. State transitions

Não há transições de estado a modelar — preferências são updates idempotentes (sobrescrita) e a árvore de navegação é estática. Sem máquina de estado.

# Instruções para Claude — Rehidratação no novo ambiente

**Para você (Claude) ler na PRIMEIRA sessão no servidor remoto**:

Você está continuando trabalho de uma sessão anterior do Claude Code que rodou no WSL Ubuntu da máquina local do owner (`/home/lucas/projetos/paciente-360`). O projeto foi migrado para este servidor remoto e você precisa **rehidratar a memória + retomar de onde paramos**.

---

## Passo 1 — Leia estes arquivos (nesta ordem)

```
docs/claude-context/HANDOFF.md          # estado completo: branches, commits, fases entregues
docs/claude-context/BACKLOG.md          # débitos técnicos abertos + smoke pendente + Fase 7 sugerida
docs/claude-context/PENDING-DECISIONS.md # 8 decisões abertas (D1-D8) que dependem do owner
docs/claude-context/memory/MEMORY.md     # índice da memória — aponta para os 5 project_*.md
docs/claude-context/memory/project_paciente360.md  # escopo + stack do produto
docs/claude-context/memory/project_fase0_done.md   # Fase 0 — Fundação multi-tenant
docs/claude-context/memory/project_fase2_done.md   # Fase 2 — CRM Pacientes
docs/claude-context/memory/project_fase4_done.md   # Fase 4 — Token Auth Migration
docs/claude-context/memory/project_fase5_done.md   # Fase 5 — Agendamento Consultas
```

(Não há `project_fase1_done.md` nem `project_fase3_done.md` — foram entregues mas não ficaram no memory durante a sessão original. Detalhes em `HANDOFF.md`.)

---

## Passo 2 — Recrie a memória local NESTE ambiente

A memória do Claude fica em `~/.claude/projects/<HASH-DO-PATH>/memory/` onde HASH é derivado do path absoluto do projeto. Como o path mudou (provavelmente `/home/lucas/...` → algo como `/srv/paciente-360/` ou `/home/dev/paciente-360/`), o HASH é diferente e a memória copiada não é encontrada automaticamente.

**Solução**:
1. Descobrir o novo HASH path:
   ```bash
   # Rode este Python helper para descobrir o HASH
   python3 -c "import hashlib; print(hashlib.sha256('$(pwd)'.encode()).hexdigest()[:16])"
   ```
   OU simplesmente: `ls ~/.claude/projects/` e procure pelo diretório que match com seu path atual (formato `-srv-paciente-360` ou similar).

2. Copie os 6 arquivos de `docs/claude-context/memory/` para `~/.claude/projects/<NOVO-HASH-OU-NOME-PATH>/memory/`:
   ```bash
   mkdir -p ~/.claude/projects/$(echo "$(pwd)" | sed 's|/|-|g')/memory/
   cp docs/claude-context/memory/*.md ~/.claude/projects/$(echo "$(pwd)" | sed 's|/|-|g')/memory/
   ```

3. Confirme com o usuário que a memória está acessível antes de prosseguir.

---

## Passo 3 — Confirme estado git esperado

Esperado:
- Branch atual: `006-agenda-ux-polish` (ou outra que o user esteja)
- Branches em origin: `main`, `005-agendamento-consultas`, `006-agenda-ux-polish`
- Last commit branch atual: `25e5b79` (último commit da Fase 6) OU mais novo se user já trabalhou

```bash
git log --oneline -5
git branch -a
git status
```

Se aparecer commits que você não conhece (commits feitos entre a migração e agora), pergunte ao user o que aconteceu antes de continuar.

---

## Passo 4 — Smoke validation pós-migração (recomendado)

Antes de iniciar trabalho novo:

```bash
# 1. Sail rodando
vendor/bin/sail up -d
vendor/bin/sail ps   # confirma horizon, laravel.test, mailpit, pgsql, redis, reverb healthy

# 2. Deps instaladas
vendor/bin/sail composer install
vendor/bin/sail npm install --legacy-peer-deps

# 3. .env configurado (ver docs/setup/onboarding-dev.md)
cp .env.example .env  # se não existe
vendor/bin/sail artisan key:generate
# editar .env e setar DB, REDIS, GOOGLE_CALENDAR_*, etc.

# 4. Migrations + seeders
vendor/bin/sail artisan migrate
vendor/bin/sail artisan db:seed --class=AgendaPermissionsSeeder

# 5. Suite full — confirma 1167/1164/0
vendor/bin/sail artisan test --compact
```

Se algum passo falhar, **reporte ao user antes de mexer em código** — pode ser problema de migração que precisa ser resolvido primeiro.

---

## Passo 5 — Apresente ao user um resumo curto + opções de próximos passos

Diga algo como:

> "Memória rehidratada. Estado verificado:
> - Branch atual: `<branch>`
> - Suite: `<X/Y>` tests
> - Pré-requisitos: `<status>`
>
> Há 5 caminhos possíveis (ver BACKLOG.md):
> 1. Smoke E2E Fase 5 staging (gate humano para mergear)
> 2. Smoke visual Fase 6 nas 6 telas refinadas (mobile + teclado + rede offline)
> 3. Resolver decisões pendentes (D1-D8 em PENDING-DECISIONS.md)
> 4. Pagar débitos técnicos (cache Redis SlotGenerator, listeners reais inbox, expand tests)
> 5. Iniciar Fase 7 (Gestão de Retornos + Outlook sync + Receituários)
>
> Qual caminho seguimos?"

---

## Passo 6 — Mantenha as memórias atualizadas

Após cada commit/feature significativa:
- Atualize `MEMORY.md` index
- Crie/atualize `project_faseN_done.md` quando uma fase completar
- Edite `HANDOFF.md` quando o estado da sessão mudar materialmente
- Edite `BACKLOG.md` quando débitos forem pagos OU novos descobertos
- Edite `PENDING-DECISIONS.md` quando decisões forem resolvidas OU novas surgirem

Estes arquivos VIVEM no git — futuras migrações replicam a mesma rehidratação.

---

## Notas operacionais

- **Não pushe automaticamente** salvo se o user pedir explicitamente
- **Respeite branches existentes** — Fase 5 (`005-...`) está aguardando smoke E2E + merge; Fase 6 (`006-...`) aguardando smoke visual + PR
- **Spec Kit é a forma preferida** de iniciar features novas — `/speckit.specify` → `/speckit.clarify` → `/speckit.plan` → `/speckit.tasks` → `/speckit.implement`
- **Para refinements pequenos** (< 30 tasks), Spec Kit retroativo manual (criar specs/XXX/spec.md + plan.md + tasks.md sem rodar skills interativos) é aceitável — ver D4 em PENDING-DECISIONS.md
- **Agentes especializados** estão em `.claude/agents/` — todos versionados no git. Use `vue-frontend-engineer` em modo UX quality para refinements (heurística completa em `.claude/agents/frontend-ux-quality.md`)

Boa continuidade.

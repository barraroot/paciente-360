# ralph.sh — Loop autônomo para o CRM Médico SaaS

Script no estilo Ralph Wiggum para executar tasks do Spec Kit uma a uma, em fresh context, até a fase ficar pronta. Inspirado no trabalho de Geoffrey Huntley e Matt Pocock; adaptado para o seu fluxo Spec Kit + Laravel + Vue.

## Pré-requisitos

```bash
# Claude Code CLI instalado e logado
claude --version

# Spec Kit já inicializado e fase com tasks.md gerado
ls .specify/memory/constitution.md
ls specs/001-fundacao-multitenant/tasks.md

# tornar o script executável
chmod +x ralph.sh
```

## Como funciona

O Ralph não é mágico. Ele faz uma coisa só, repetidamente:

1. Lê `tasks.md` da fase ativa (detectada pela branch Git, ex.: `001-fundacao-multitenant`).
2. Chama o Claude Code em modo headless (`claude --print`) com um prompt fixo.
3. O Claude escolhe a próxima task `[ ]` cujas dependências estejam `[x]`, executa, testa, marca como `[x]` e commita.
4. O loop reinicia com contexto limpo. Tudo que persiste está no disco: `tasks.md`, `progress.md`, `blockers.md`, código.
5. Para quando: tudo `[x]`, ou Claude emite `RALPH_FASE_COMPLETA`, ou 3 iterações sem progresso, ou bate `MAX_ITERATIONS`.

A regra de ouro: **estado mora no disco, não no contexto**. É por isso que cada iteração começa do zero sem perder nada.

## Uso

```bash
# Coloque-se na branch da fase
git checkout 001-fundacao-multitenant

# Rode o loop com defaults (50 iterações, spec inferido pela branch)
./ralph.sh

# Ou limite as iterações (use 10-20 nas primeiras execuções)
./ralph.sh 15

# Forçando um spec específico (útil se a branch não bate com a convenção)
./ralph.sh 30 specs/001-fundacao-multitenant
```

## Acompanhamento em outro terminal

```bash
# Log consolidado
tail -f .ralph/logs/ralph.log

# Última iteração completa
tail -f .ralph/logs/iter-001.log

# Progresso de tasks
watch -n 5 'grep -c "^- \[x\]" specs/001-fundacao-multitenant/tasks.md'

# Commits gerados pelo Ralph
git log --oneline --since="1 hour ago"
```

## Estrutura criada pelo Ralph

```
.ralph/
├── PROMPT.md           # prompt fixo lido a cada iteração (gerado pelo script)
├── progress.md         # histórico de tasks concluídas (criado pelo Claude)
├── blockers.md         # tasks que precisam de decisão humana (criado pelo Claude)
├── sterile_count       # contador interno de iterações estéreis
└── logs/
    ├── ralph.log       # log consolidado de todas as iterações
    └── iter-NNN.log    # output bruto do Claude por iteração
```

## Convenções que o Ralph espera no `tasks.md`

```markdown
## T001: Bootstrap Laravel 11 + Docker Compose
- [ ] Estado: pendente (Ralph executa)
- [x] Estado: concluída (Ralph pula)
- [B] Estado: bloqueada (Ralph não tenta de novo)

Dependências: nenhuma
Critérios: app/ criada; docker compose up funcional; migrate:fresh ok
```

O Claude lê os checkboxes e as dependências para decidir o que rodar. Se o seu `tasks.md` foi gerado pelo `/speckit.tasks`, o formato já está nesse padrão.

## Regras de segurança embutidas no prompt

- Nunca `git push --force`
- Nunca deletar migrations já aplicadas
- Nunca editar `.specify/memory/constitution.md`
- Nunca tocar em outras fases (`specs/0NN-...` que não a ativa)
- Tasks ambíguas viram `[B]` em vez de `[x]` + entrada em `blockers.md`
- 3 tasks bloqueadas consecutivas → loop para para revisão humana

## Quando NÃO usar o Ralph

- Fases `/speckit.specify`, `/speckit.clarify`, `/speckit.plan` — exigem decisões de produto/arquitetura suas. Rode no chat interativo.
- Tasks que envolvem credenciais reais (Stripe live, WhatsApp Cloud com número de produção). Faça à mão.
- Migrations em ambiente de produção. Sempre.
- Quando você ainda não tem testes mínimos. O Ralph confia nos testes para validar progresso.

## Ajustes finos

**Mais conservador (Claude pede confirmação a cada edição):**

No script, troque:
```bash
--permission-mode acceptEdits
```
por:
```bash
--permission-mode default
```

**Modelo específico:**

Adicione `--model claude-opus-4-7` (ou o que preferir) na chamada do Claude.

**Worktree para não atrapalhar seu working directory:**

```bash
git worktree add ../crm-ralph 001-fundacao-multitenant
cd ../crm-ralph
./ralph.sh
```

Assim você continua trabalhando no repo principal enquanto o Ralph roda em paralelo.

## Custo

Em iterações mecânicas (CRUDs, testes simples, migrations), uma fase de ~30 tasks costuma rodar em 25-40 iterações. Cada iteração consome ~10-30k tokens dependendo do tamanho do contexto lido. Faça as contas no preço atual da API antes de deixar rodando à noite.

## Recuperação

Se o loop morrer no meio (rede, máquina dormiu, Ctrl+C), basta rodar `./ralph.sh` de novo. Como o estado está em `tasks.md` e os commits já foram feitos, ele retoma de onde parou.

## Ordem recomendada das fases

Sempre na branch correspondente:

```bash
git checkout 001-fundacao-multitenant   && ./ralph.sh
git checkout 002-crm-pacientes          && ./ralph.sh
git checkout 003-inbox-whatsapp         && ./ralph.sh
git checkout 004-ia-matricial           && ./ralph.sh
git checkout 005-agenda-retornos        && ./ralph.sh
git checkout 006-receituarios           && ./ralph.sh
git checkout 007-campanhas-relatorios   && ./ralph.sh
git checkout 008-integracoes-superadmin-lgpd && ./ralph.sh
```

Entre fases, faça merge na main, rode `/speckit.analyze` para validar e siga.

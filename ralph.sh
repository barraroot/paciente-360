#!/usr/bin/env bash
# ralph.sh — Ralph Wiggum loop para executar tasks do Spec Kit, uma de cada vez.
#
# Filosofia: cada iteração roda Claude Code em fresh context, lê tasks.md,
# escolhe a próxima task pendente, implementa, testa, commita, sai.
# O loop reinicia até não restar task pendente ou bater max_iterations.
#
# Uso:
#   ./ralph.sh                                      # fase ativa = branch atual, 50 iterações
#   ./ralph.sh 30                                   # 30 iterações
#   ./ralph.sh 30 specs/001-fundacao-multitenant   # path do spec explícito
#
# Pré-requisitos: claude (CLI), git, jq, e um spec já com tasks.md gerado.

set -euo pipefail

# ─────────────────────────────────────────────────────────────────────
# Configuração
# ─────────────────────────────────────────────────────────────────────
MAX_ITERATIONS="${1:-50}"
SPEC_PATH="${2:-}"
COMPLETION_SIGNAL="RALPH_FASE_COMPLETA"
LOG_DIR=".ralph/logs"
STATE_FILE=".ralph/state.json"
PROMPT_FILE=".ralph/PROMPT.md"

mkdir -p "$LOG_DIR" .ralph
ITERATION=0
TIMESTAMP() { date +"%Y-%m-%d %H:%M:%S"; }
LOG() { echo "[$(TIMESTAMP)] $*" | tee -a "$LOG_DIR/ralph.log"; }

# ─────────────────────────────────────────────────────────────────────
# Detecta a fase ativa pela branch (convenção Spec Kit: NNN-slug)
# ─────────────────────────────────────────────────────────────────────
if [[ -z "$SPEC_PATH" ]]; then
    BRANCH=$(git branch --show-current)
    if [[ ! "$BRANCH" =~ ^[0-9]{3}- ]]; then
        echo "❌ Branch atual ('$BRANCH') não segue o padrão Spec Kit (NNN-slug)."
        echo "   Faça checkout para uma branch de fase, ex.: git checkout 001-fundacao-multitenant"
        exit 1
    fi
    SPEC_PATH="specs/$BRANCH"
fi

if [[ ! -f "$SPEC_PATH/tasks.md" ]]; then
    echo "❌ Não encontrei $SPEC_PATH/tasks.md"
    echo "   Rode /speckit.tasks no Claude Code antes de iniciar o Ralph."
    exit 1
fi

LOG "🟢 Iniciando Ralph"
LOG "   Spec ativo: $SPEC_PATH"
LOG "   Max iterações: $MAX_ITERATIONS"
LOG "   Sinal de conclusão: $COMPLETION_SIGNAL"

# ─────────────────────────────────────────────────────────────────────
# Prompt: o coração do Ralph. Mesmo prompt em toda iteração.
# Claude lê o tasks.md e decide o que fazer SOZINHO a cada vez.
# ─────────────────────────────────────────────────────────────────────
cat > "$PROMPT_FILE" <<PROMPT_EOF
Você é um agente autônomo executando o Ralph Wiggum loop para o projeto CRM Médico SaaS.
Cada iteração começa com contexto limpo. Leia os artefatos do disco para entender onde paramos.

CONTEXTO PERMANENTE (leia SEMPRE no início):
1. .specify/memory/constitution.md — princípios não negociáveis
2. ${SPEC_PATH}/spec.md — o quê e por quê desta fase
3. ${SPEC_PATH}/plan.md — stack e arquitetura
4. ${SPEC_PATH}/data-model.md — modelo de dados
5. ${SPEC_PATH}/tasks.md — backlog de tasks com checkboxes
6. .ralph/progress.md (se existir) — histórico de tasks concluídas em iterações anteriores

REGRA DE ESCOLHA DA TASK:
- Identifique a PRIMEIRA task em ${SPEC_PATH}/tasks.md cujo checkbox esteja [ ] (pendente).
- Se a task tiver dependências (outras task IDs), confirme que TODAS as dependências estão [x] concluídas.
- Se nenhuma task elegível existir (tudo [x] ou tudo bloqueado por dependência), escreva exatamente "${COMPLETION_SIGNAL}" como ÚLTIMA linha da sua resposta e encerre.
- Se houver uma task de teste correspondente à task de implementação que você escolheu, faça a task de teste PRIMEIRO (TDD obrigatório pela constituição).

EXECUÇÃO DA TASK ESCOLHIDA:
1. Anuncie qual task vai executar e por quê (ID, título, dependências verificadas).
2. Implemente seguindo as convenções do plan.md (estrutura de pastas, Form Requests, API Resources, Policies, etc.).
3. Rode os testes relevantes:
   - Backend Laravel: ./vendor/bin/pest --filter=<grupo relevante>
   - Frontend Vue: npm run test -- --run <arquivo relevante>
   - Lint: ./vendor/bin/pint --test e npm run lint
4. Se algum teste falhar, CORRIJA na mesma iteração antes de prosseguir. Não commite código quebrado.
5. Atualize o checkbox da task em ${SPEC_PATH}/tasks.md de [ ] para [x].
6. Adicione uma linha em .ralph/progress.md (crie o arquivo se não existir) no formato:
   - YYYY-MM-DD HH:MM | <ID> | <título curto> | <hash do commit>
7. Faça commit com mensagem convencional:
   git add -A
   git commit -m "feat(<escopo>): <descrição> (<task_id>)"

REGRAS DE SEGURANÇA:
- NUNCA force push.
- NUNCA delete migrations já aplicadas. Crie uma nova migration de correção.
- NUNCA mexa em arquivos fora desta fase (ex.: outras pastas em specs/).
- NUNCA edite .specify/memory/constitution.md — princípios são imutáveis no Ralph.
- Se uma task depende de decisão humana (NEEDS_CLARIFICATION ou ambiguidade nova descoberta),
  NÃO adivinhe. Marque a task como bloqueada com [B] em vez de [x], registre em
  .ralph/blockers.md o que precisa de decisão, e prossiga para a próxima task elegível.
- Se 3 tasks consecutivas ficarem bloqueadas, escreva "${COMPLETION_SIGNAL}" e pare —
  é hora de eu (humano) revisar.

ESTILO:
- Seja conciso nos logs. Foco em o que mudou, não em explicações longas.
- Português pt-BR em mensagens de commit, comentários de código e docs.
- Código segue PSR-12 (PHP) e Vue 3 style guide.

Comece agora. Escolha a próxima task e execute-a.
PROMPT_EOF

LOG "📝 Prompt gerado em $PROMPT_FILE"

# ─────────────────────────────────────────────────────────────────────
# Loop principal
# ─────────────────────────────────────────────────────────────────────
while [[ $ITERATION -lt $MAX_ITERATIONS ]]; do
    ITERATION=$((ITERATION + 1))
    ITER_LOG="$LOG_DIR/iter-$(printf '%03d' $ITERATION).log"

    LOG "─────────────────────────────────────────────"
    LOG "🔁 Iteração $ITERATION/$MAX_ITERATIONS"
    LOG "─────────────────────────────────────────────"

    # Snapshot do progresso antes da iteração
    PENDING_BEFORE=$(grep -c '^- \[ \]' "$SPEC_PATH/tasks.md" || echo 0)
    DONE_BEFORE=$(grep -c '^- \[x\]' "$SPEC_PATH/tasks.md" || echo 0)
    LOG "   Tasks pendentes: $PENDING_BEFORE | concluídas: $DONE_BEFORE"

    if [[ "$PENDING_BEFORE" -eq 0 ]]; then
        LOG "✅ Nenhuma task pendente. Encerrando loop."
        break
    fi

    # Roda Claude Code em fresh context, lendo o prompt do arquivo
    # --print: modo headless (não interativo)
    # --permission-mode acceptEdits: aceita edições sem perguntar (cuidado: revise commits)
    # Se quiser modo mais conservador, troque por: --permission-mode default
    set +e
    cat "$PROMPT_FILE" | claude \
        --print \
        --permission-mode acceptEdits \
        --output-format text \
        > "$ITER_LOG" 2>&1
    EXIT_CODE=$?
    set -e

    if [[ $EXIT_CODE -ne 0 ]]; then
        LOG "⚠️  Claude saiu com código $EXIT_CODE. Veja $ITER_LOG"
        # Não aborta — Ralph é persistente. Próxima iteração tenta de novo.
        sleep 5
        continue
    fi

    # Verifica sinal de conclusão na saída
    if tail -n 5 "$ITER_LOG" | grep -q "$COMPLETION_SIGNAL"; then
        LOG "🏁 Sinal de conclusão recebido: $COMPLETION_SIGNAL"
        break
    fi

    # Snapshot pós-iteração
    PENDING_AFTER=$(grep -c '^- \[ \]' "$SPEC_PATH/tasks.md" || echo 0)
    DONE_AFTER=$(grep -c '^- \[x\]' "$SPEC_PATH/tasks.md" || echo 0)
    DELTA=$((DONE_AFTER - DONE_BEFORE))

    if [[ $DELTA -eq 0 ]]; then
        LOG "⚠️  Nenhuma task concluída nesta iteração. Verifique $ITER_LOG"
        # Conta iterações estéreis consecutivas
        STERILE_FILE=".ralph/sterile_count"
        STERILE=$(cat "$STERILE_FILE" 2>/dev/null || echo 0)
        STERILE=$((STERILE + 1))
        echo "$STERILE" > "$STERILE_FILE"
        if [[ $STERILE -ge 3 ]]; then
            LOG "🛑 3 iterações consecutivas sem progresso. Abortando para revisão humana."
            break
        fi
    else
        echo 0 > .ralph/sterile_count
        LOG "✅ +$DELTA task(s) concluída(s) nesta iteração."
    fi

    # Pequeno delay para não martelar a API e dar tempo de Ctrl+C
    sleep 2
done

# ─────────────────────────────────────────────────────────────────────
# Relatório final
# ─────────────────────────────────────────────────────────────────────
LOG "─────────────────────────────────────────────"
LOG "📊 Relatório final"
LOG "─────────────────────────────────────────────"
FINAL_PENDING=$(grep -c '^- \[ \]' "$SPEC_PATH/tasks.md" || echo 0)
FINAL_DONE=$(grep -c '^- \[x\]' "$SPEC_PATH/tasks.md" || echo 0)
FINAL_BLOCKED=$(grep -c '^- \[B\]' "$SPEC_PATH/tasks.md" || echo 0)
LOG "   Concluídas: $FINAL_DONE"
LOG "   Pendentes:  $FINAL_PENDING"
LOG "   Bloqueadas: $FINAL_BLOCKED"
LOG "   Iterações executadas: $ITERATION"
LOG "   Logs por iteração: $LOG_DIR/"
[[ -f .ralph/blockers.md ]] && LOG "   ⚠️  Há bloqueadores em .ralph/blockers.md — revise."

if [[ "$FINAL_PENDING" -eq 0 && "$FINAL_BLOCKED" -eq 0 ]]; then
    LOG "🎉 Fase concluída."
    exit 0
else
    LOG "🟡 Fase ainda incompleta — rode novamente após resolver bloqueadores."
    exit 1
fi

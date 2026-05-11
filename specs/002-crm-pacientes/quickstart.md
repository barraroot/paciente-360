# Quickstart: Fase 2 — CRM Pacientes

**Branch**: `002-crm-pacientes` | **Data**: 2026-05-11

Guia para subir, popular e validar manualmente as 5 user stories da Fase 2 em ambiente local Sail. Pré-requisito: Fase 0 entregue e funcional (`clinica-alfa.lvh.me` navegável).

## 0. Pré-requisitos

```bash
# já existentes da Fase 0 — apenas confirme
vendor/bin/sail up -d
vendor/bin/sail artisan migrate:fresh --seed --class=DevSeeder
# clinica-alfa.lvh.me e clinica-beta.lvh.me devem estar navegáveis
# senha dev: password123
```

## 1. Migrations e extensões PG novas

```bash
# após implementação:
vendor/bin/sail artisan migrate

# verifica extensões pg_trgm e unaccent
vendor/bin/sail artisan db:show
vendor/bin/sail artisan tinker --execute 'echo DB::selectOne("SELECT * FROM pg_extension WHERE extname IN ('"'"'pg_trgm'"'"','"'"'unaccent'"'"')")->extname;'
# deve listar pg_trgm e unaccent
```

## 2. Seed de pacientes para testar funil/busca

```bash
# DevSeeder estendido (esta fase) cria ~30 pacientes em clinica-alfa
vendor/bin/sail artisan db:seed --class=DevSeeder

# confirma:
vendor/bin/sail artisan tinker --execute 'echo App\Models\Paciente::query()->withoutGlobalScopes()->count();'
# >= 30
```

## 3. Fluxo manual — Cadastro (US-3.1)

1. Login em `http://clinica-alfa.lvh.me/login` como admin-clinica.
2. Navegar para `/panel/pacientes` → botão "Novo Paciente".
3. Preencher form:
   - Nome: `Maria Silva`
   - CPF: `123.456.789-09` (CPF válido por DV)
   - Telefone: `(31) 99999-1111`
   - Status: `lead`
   - Origem: `indicacao`
   - Profissional responsável: qualquer da lista
4. Submeter → redireciona para ficha do paciente.
5. **Validar**:
   - Ficha mostra `Maria Silva`, CPF formatado, status `Lead`.
   - Aba "Timeline" mostra evento `paciente.criado`.
6. **Testar dedup**: criar outro paciente com mesmo CPF → modal de dedup aparece com 3 opções (mesclar / criar paralelo / abrir existente).

## 4. Fluxo manual — Timeline + Anotações (US-3.2)

1. Na ficha do paciente, abrir aba "Timeline".
2. Adicionar anotação:
   - Tipo: `clinica`
   - Texto: "Paciente relata cefaleia recorrente; orientação para retorno em 7 dias."
3. **Validar**:
   - Evento `anotacao.criada` aparece no topo.
   - Logout/login como `atendente@clinica-alfa.com.br` (role atendente) → anotação clínica **não aparece** (visibilidade por perfil).
4. **Testar retratação**: voltar como admin, clicar "Adicionar retratação" na anotação → submeter texto explicativo → novo evento `anotacao.retratada` linkado.

## 5. Fluxo manual — Importação (US-3.3)

1. Baixar template em `/panel/pacientes/importar` → botão "Baixar template".
2. Preencher template com 10 linhas válidas + 3 inválidas (CPF errado, telefone vazio).
3. Upload + escolha "Status inicial: lead".
4. **Validar**:
   - Listagem `/panel/pacientes/importar/{id}` mostra progresso em tempo real.
   - Ao concluir (status `completed` ou `partial_failure`), relatório lista 10 importadas + 3 com erro com motivos.
5. **Testar reimport**: enviar mesmo arquivo novamente → pacientes existentes não duplicam; campos vazios são atualizados.
6. **Testar limite**: arquivo > 5 MB ou > 10.000 linhas → 413/422.

## 6. Fluxo manual — Funil Kanban (US-3.4)

1. Navegar para `/panel/funil`.
2. **Primeira vez** → template padrão é seedado automaticamente: 5 colunas (Novo, Qualificado, Agendado, Compareceu, Perdido).
3. Drag-and-drop um card de "Novo" para "Qualificado".
4. **Validar**:
   - Card persiste a nova posição em refresh.
   - Timeline do paciente registra `funil.movimentacao`.
5. **Testar motivo obrigatório**: mover card para "Perdido" → modal exige seleção entre `sem_interesse`, `sem_retorno`, `preco`, `outro` (com texto livre).
6. **Customizar colunas**: `/panel/funil/configuracao` → renomear "Compareceu" para "Convertido"; mudança reflete no Kanban.

## 7. Fluxo manual — Tags e Status (US-3.5)

1. Na ficha de um paciente, aba "Detalhes" → seção Tags.
2. Aplicar tag nova: `Diabético` → criada como tag livre.
3. Aplicar `DIABETICO` em outro paciente → reusa a mesma tag (normalização case+accent-insensitive).
4. Aplicar tags até a 11ª → aviso UX "Recomendamos no máximo 10 tags".
5. Mudar status do paciente de `lead` para `bloqueado`:
   - Como admin-clinica → permitido.
   - Como medico → 403 (transição só para Admin).
6. **Validar**: timeline registra `paciente.status_alterado` com `status_anterior=lead` e `status_novo=bloqueado`.

## 8. Exportação + Audit (LGPD)

1. Como admin-clinica, em `/panel/pacientes`, aplicar filtros (ex.: `?tag=diabetico&status=ativo`).
2. Botão "Exportar CSV" → download imediato.
3. **Validar audit**:
   ```bash
   vendor/bin/sail artisan tinker --execute "echo App\Models\AuditLog::where('action', 'paciente.exported')->latest()->first()->payload;"
   ```
   - Deve ter `executor_id`, `escopo` (filtros), `contagem`, `arquivo_hash` (SHA-256), `tamanho_bytes`. **NÃO** deve ter PII.
4. **Testar permissão**: logar como medico → botão "Exportar CSV" não aparece; tentar API direto → 403.

## 9. Mesclagem reversível

1. Cadastrar 2 pacientes com mesmo CPF (forçando "criar paralelo" no modal de dedup).
2. Ir para `/panel/pacientes/mesclagem` → selecionar os 2.
3. Resolver campos conflitantes na tela visual → confirmar.
4. **Validar**:
   - Apenas o paciente alvo aparece em listagens.
   - Timeline do alvo tem evento `mesclagem.executada`.
5. Em até 30 dias, ir em `/panel/pacientes/mesclagens/{id}` → botão "Reverter".
6. **Validar**: ambos os pacientes voltam às listagens; evento `mesclagem.revertida` aparece nas timelines.

## 10. Testes automatizados

```bash
# Suite Fase 2 — todos verdes
vendor/bin/sail artisan test --compact tests/Feature/Fase2/

# Regressão Fase 0
vendor/bin/sail artisan test --compact

# Pint
vendor/bin/sail bin pint --dirty --format agent

# OpenAPI drift
vendor/bin/sail artisan openapi:check

# Coverage
vendor/bin/sail artisan test --coverage --min=75 tests/Feature/Fase2/
```

## 11. E2E (Playwright)

```bash
# 1 jornada nova: cadastro → tag → anotação → funil
vendor/bin/sail npx playwright test tests/e2e/crm-paciente-jornada-completa.spec.ts

# Pré-requisito: vendor/bin/sail up + migrate:fresh --seed
```

## 12. Troubleshooting

| Sintoma | Causa provável | Resolução |
|---|---|---|
| Busca por similaridade retorna vazio | Extensão `pg_trgm` não habilitada | `vendor/bin/sail artisan migrate` (migration de extensão é idempotente) |
| Importação travada em `processing` | Worker Horizon caiu | `vendor/bin/sail artisan horizon:status` + `vendor/bin/sail artisan queue:retry all` |
| Tag duplicada criada | Normalização não foi aplicada | Verificar coluna `nome_normalizado` no model |
| Timeline lenta com >1k eventos | Index `(tenant_id, paciente_id, created_at DESC)` ausente | `vendor/bin/sail artisan db:show eventos_timeline` |
| 403 ao exportar com Admin Clínica | Spatie team_id não setado para tenant | Verificar listener `TenantResolved`/`Authenticated` (Fase 0) |

## 13. URLs locais úteis

- `http://clinica-alfa.lvh.me/panel/pacientes` — lista
- `http://clinica-alfa.lvh.me/panel/pacientes/novo` — cadastro
- `http://clinica-alfa.lvh.me/panel/pacientes/{id}` — ficha + timeline
- `http://clinica-alfa.lvh.me/panel/pacientes/importar` — importação
- `http://clinica-alfa.lvh.me/panel/funil` — Kanban
- `http://clinica-alfa.lvh.me/panel/convenios` — catálogo
- `http://localhost:8025` — Mailpit (não tem mail nesta fase, mas pode ser usado se notificações forem adicionadas)
- `http://crm.lvh.me/admin` — painel super admin (métricas agregadas; nenhuma PII de paciente)

## 14. Definição de Pronto (cross-ref do spec § 9)

Verificar **antes** de mergear:

- [ ] `migrate:fresh --seed` cria 11 tabelas novas + 30 pacientes seedados em clinica-alfa.
- [ ] Suite Fase 2: ≥ 100 testes novos verdes.
- [ ] Coverage Fase 2 ≥ 75%, global ≥ 70%.
- [ ] `openapi:check` exit 0.
- [ ] Pint clean.
- [ ] 1 E2E Playwright verde.
- [ ] `TenantIsolationTest` cobre 100% dos 22 endpoints novos.
- [ ] `pg_trgm` + `unaccent` habilitados em PG; migration idempotente.
- [ ] Documentação `quickstart.md` atualizada com observações reais (este arquivo).
- [ ] AC-3.x.y individuais rastreados em `tasks.md` com pelo menos 1 teste cada.

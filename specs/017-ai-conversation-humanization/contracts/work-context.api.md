# Contract — Work Context API (US2)

REST na API tenant (`/api/v1`), dentro do grupo `prefix('ai')` + `name('ai.')` existente (Fase 15). Middleware: `auth:sanctum` + `tenant.slug` + `tenant.not-suspended` (herdados do grupo). Pipeline obrigatório `Form Request → Controller → Service → Resource`.

**Permissões** (granulares `ai.*`, mesma convenção de `ai.persona.view` etc.):
- `GET /ai/work-context` → `Gate::authorize('ai.work-context.view')`
- `PUT /ai/work-context` → `ai.work-context.manage` (verificado no `UpsertWorkContextRequest::authorize()`)

As novas abilities `ai.work-context.view` e `ai.work-context.manage` MUST ser registradas no catálogo de permissões (Spatie) e atribuídas aos papéis que já gerem IA (ex.: Admin Clínica), seguindo o registro das demais `ai.*`.

Singleton por tenant (não há `{id}` na rota — sempre o contexto da clínica autenticada).

## GET `/api/v1/ai/work-context`

Retorna o contexto de trabalho da clínica (ou `null`/default vazio se ainda não configurado).

**200**:
```json
{
  "data": {
    "services": [{ "nome": "Consulta enxaqueca/cefaleia", "descricao": "Avaliação individualizada ~1h" }],
    "pricing": [{ "item": "Consulta", "valor_a_vista": "R$300", "valor_cartao": "R$330" }],
    "locations": [{ "cidade": "Aracaju", "endereco": "Centro Médico Jardim Europa" }, { "cidade": "Itabaiana" }],
    "deposit_policy": { "exige_sinal": true, "percentual": 20, "meio": "PIX", "texto": "Sinal de 20% abatido na consulta" },
    "tone": "acolhedor, com emojis 💛",
    "qualification_questions": [
      "Com que frequência as crises acontecem?",
      "Essas dores atrapalham seu trabalho/rotina?",
      "Você já investigou isso com um médico antes?"
    ],
    "free_form": "A Dra. realiza avaliação cuidadosa e individualizada...",
    "version": 3,
    "is_active": true,
    "updated_at": "2026-05-27T12:00:00-03:00"
  }
}
```

## PUT `/api/v1/ai/work-context`

Cria/atualiza (upsert) o contexto. Incrementa `version`. Validação `UpsertWorkContextRequest`:

- `services` array de `{nome:required, descricao?}`.
- `pricing` array de `{item:required, valor_a_vista?, valor_cartao?, observacao?}` — strings (não números clínicos).
- `locations` array de `{cidade:required, endereco?, observacao?}`.
- `deposit_policy` `{exige_sinal:bool, percentual?:int 0..100, meio?, texto?}`.
- `tone` string ≤120.
- `qualification_questions` array de string (≤ `config('ai.matricial.work_context.max_questions')`).
- `free_form` string (≤ N chars).
- **Allow-list não-clínica**: o request REJEITA qualquer chave clínica (diagnóstico, prescrição, posologia etc.) — Princípio III.

**200**: mesmo shape do GET (já com `version` incrementada).
**422**: erros de validação por campo.
**403**: sem permissão.

## Isolamento (FR-012)

- O contexto é resolvido **sempre** pelo tenant autenticado; não há acesso por id arbitrário → impossível ler/editar de outra clínica.
- Teste obrigatório: tenant B não enxerga nem sobrescreve o contexto do tenant A.

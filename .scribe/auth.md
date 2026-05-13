# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer paciente360_<seu-token>"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Autentique-se com `POST /api/v1/auth/login` enviando `email` + `password`. A resposta inclui um campo `token` (Sanctum Personal Access Token, prefixo `paciente360_`). Inclua-o em **todas** as requests autenticadas:

```
Authorization: Bearer paciente360_<seu-token>
X-Tenant-Slug: <slug-da-clinica>
```

O header `X-Tenant-Slug` é obrigatório em rotas autenticadas (exceto `/auth/login`) — triple-check anti-token-roubo cross-tenant (FR-011 / Princípio II).

Tokens expiram em 30 dias com *sliding expiration*: cada request renova `expires_at` quando restam < 5 dias.

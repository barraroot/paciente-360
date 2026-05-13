# Introduction

API REST do Paciente360 — CRM médico SaaS multi-tenant. A partir da Fase 4 (token auth migration), todos os endpoints autenticados usam Bearer Sanctum tokens e exigem o header `X-Tenant-Slug` para resolver o tenant alvo da request.

<aside>
    <strong>Base URL</strong>: <code>https://9392-177-18-76-77.ngrok-free.app</code>
</aside>

    Esta documentação cobre o pipeline pós-Fase 4 (Bearer). Para integrar:

    1. `POST /api/v1/auth/login` — receba o token Bearer.
    2. Envie `Authorization: Bearer <token>` + `X-Tenant-Slug: <slug>` em todas as requests autenticadas.
    3. Gerencie sessões em `/api/v1/auth/tokens` (listagem) e `DELETE /api/v1/auth/tokens/{id}` (revogação).

    Uma collection Postman oficial com pre-request scripts está disponível em `docs/api/Paciente360-API-v1.postman_collection.json` (auto-injeta Bearer + X-Tenant-Slug + salva o token após login).


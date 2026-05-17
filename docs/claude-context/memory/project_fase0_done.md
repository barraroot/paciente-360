---
name: Paciente360 — Fase 0 entregue
description: Fase 0 (fundação multi-tenant) completa em 2026-05-10. 128/128 tasks, 467 testes PHPUnit, cobertura 77.2%, 4 jornadas E2E Playwright. Pronto para Fase 1.
type: project
originSessionId: 1cdd9d76-3906-42d4-90a4-7a21a1ba97a6
---
A Fase 0 do Paciente360 (`001-fundacao-multitenant`) foi entregue em 2026-05-10 via coordenação multi-agente Claude Code.

**Why:** o usuário delegou a execução end-to-end ("faça o papel do gestor e coordene a equipe até o final da feature") após apresentação do plano por lotes.

**How to apply:** ao retomar o projeto, partir do branch `001-fundacao-multitenant` com a foundation pronta. A próxima fase (US-fase-1: agenda, prontuário, omnichannel) pode reusar todos os Services existentes (`AuthenticationService`, `TenantStateService`, `SubscriptionService`, etc.) e a infra de audit/eventos `Auditable`.

**Métricas finais:**
- 467 testes PHPUnit verdes / 1292 assertions / cobertura 77.2%
- 4 E2E Playwright (cadastro+onboarding, convite+aceite, checkout Stripe, password reset)
- 0 drift OpenAPI vs rotas; Filament Super Admin em `/admin`
- Pint clean

**Desvios conhecidos do data-model original (a sincronizar quando relevante):**
1. `model_has_roles`/`model_has_permissions` ganharam `tenant_id` nullable + UNIQUE COALESCE (Spatie team mode exige).
2. `users.is_active` virou `users.status enum(invited|active|disabled)`.
3. `SubscriptionResource` expõe `stripe_status` em vez de `status` puro.
4. `AuditLog` ganhou relação `BelongsTo user()` (para `with('user')` no controller — não conflita com a decisão "no global scope").

**Item pendente para investigar (não bloqueante):** possível dupla-registração de listeners `Auditable` (manual via `Event::listen` + auto-discovery do `__invoke(Auditable)`). Atualmente funciona porque os testes contam DISTINCT, mas vale verificar `shouldDiscoverEvents()` em `EventServiceProvider`.

**Como rodar localmente:** `vendor/bin/sail up -d && vendor/bin/sail artisan migrate:fresh --seed --class=DevSeeder` cria `clinica-alfa.lvh.me` e `clinica-beta.lvh.me`. Senha dev `password123`. Super admin: env `SUPER_ADMIN_EMAIL/PASSWORD`. Painel super admin em `/admin` (qualquer host).

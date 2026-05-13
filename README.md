# Paciente360 — CRM médico SaaS multi-tenant

CRM omnichannel para clínicas com IA agêntica, LGPD por design e isolamento multi-tenant NON-NEGOTIABLE.

Stack: Laravel 13 + Vue 3 + Pinia + Sanctum Bearer + Reverb + Filament 5 + PostgreSQL 16 + Sail Docker.

## Onboarding rápido (devs novos)

```bash
git clone <repo>
cd paciente-360
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail composer install
vendor/bin/sail npm install
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
vendor/bin/sail npm run dev
```

URLs locais:
- SPA tenant: `http://app.lvh.me/login` (qualquer subdomínio `*.lvh.me` resolve para localhost)
- Filament super-admin: `http://crm.lvh.me/admin`
- API tenant: `http://api.lvh.me/api/v1/*`
- Documentação API (Scribe): `http://api.lvh.me/docs`

## Autenticação — Bearer Sanctum (Fase 4 — pós 2026-05-13)

A API tenant é **stateless** via Bearer Sanctum Personal Access Tokens. Filament admin permanece cookie-session **em domínio separado** (`crm.com.br` em prod, `crm.lvh.me` em dev).

### Fluxo de login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@clinica-alfa.com.br",
  "password": "...",
  "device_name": "Chrome on MacBook"
}
```

Resposta 201:

```json
{
  "token": "paciente360_a8b3c9d1e7f2...",
  "token_expires_at": "2026-06-12T00:00:00+00:00",
  "user": { "id": 1, "name": "...", "email": "...", "roles": ["admin-clinica"], "permissions": [...] },
  "tenant": { "id": 1, "slug": "clinica-alfa", "name": "...", "status": "active" }
}
```

### Headers obrigatórios em rotas autenticadas

```
Authorization: Bearer paciente360_a8b3c9d1e7f2...
X-Tenant-Slug: clinica-alfa
```

O `X-Tenant-Slug` é triple-check anti-token-roubo cross-tenant (FR-011 / Princípio II). Backend valida que `user.tenant_id === tenant.id` antes de chegar ao controller.

### Endpoints de gestão

| Método | Rota | Função |
|---|---|---|
| `POST` | `/auth/login` | Emite token Bearer |
| `GET` | `/auth/me` | Retorna user + tenant + metadados do token corrente |
| `POST` | `/auth/logout` | Revoga apenas o token corrente |
| `POST` | `/auth/logout-all` | Revoga todos os tokens do usuário |
| `GET` | `/auth/tokens` | Lista sessões ativas (com `is_current`) |
| `DELETE` | `/auth/tokens/{id}` | Revoga um token específico |

Sliding expiration: cada request renova `expires_at` quando restam < 5 dias (transparente).

### Postman collection

`docs/api/Paciente360-API-v1.postman_collection.json` — com pre-request scripts que auto-injetam Bearer + X-Tenant-Slug e post-response que salva o token após login.

### SPA (Vue 3 + Pinia)

Store `useAuthStore` em `resources/js/stores/auth.js`:
- `boot()` — rehidrata sessão de `localStorage['paciente360.auth.token']` no startup
- `login({ email, password })` — chama `POST /auth/login` e persiste
- `logout()` / `logoutAll()` — limpa state + localStorage
- Axios interceptor em `resources/js/lib/api.js` injeta os headers automaticamente

### Broadcasting

`/broadcasting/auth` exige Bearer + `X-Tenant-Slug` (não cookie). Echo authorizer em `resources/js/echo.js` usa o mesmo store de auth.

## Comandos úteis

```bash
vendor/bin/sail artisan test --compact            # suite full (~3min)
vendor/bin/sail bin pint --dirty --format agent   # formatação PHP
vendor/bin/sail artisan openapi:check             # drift do contrato
vendor/bin/sail artisan tinker                    # REPL Laravel
vendor/bin/sail artisan auth:tokens-purge-expired --dry-run  # housekeeping
```

## Features entregues

- Fase 0 (`001-fundacao-multitenant`) — bootstrap multi-tenant, RBAC Spatie team mode, audit imutável (467 tests)
- Fase 2 (`002-crm-pacientes`) — CRM de pacientes, importação, mesclagem, funil (650 tests)
- Fase 3 (`003-omnichannel-inbox`) — WhatsApp/Instagram/Widget + inbox unificado + Reverb (352 tests)
- Fase 4 (`004-token-auth-migration`) — Cookie→Bearer Sanctum migration + CSP estrita + CORS + decoupled deploy (suite final 1130 / 1127 passed)

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

<?php

/*
|--------------------------------------------------------------------------
| Multi-tenancy — Paciente360
|--------------------------------------------------------------------------
|
| Configuração da resolução de tenant por subdomínio (decisão Q2 do
| spec — research.md R1). O middleware `ResolveTenant` extrai o slug
| do host do request, busca o tenant correspondente e injeta em
| `app('tenant')` antes de qualquer Controller rodar.
|
| Em produção: `<slug>.crm.com.br` (TENANCY_SUBDOMAIN_SUFFIX=crm.com.br).
| Em dev/CI:  `<slug>.lvh.me` (DNS público que resolve `*.lvh.me` em
|             127.0.0.1; zero-config para o desenvolvedor).
|
| O **mesmo** code path roda em todos os ambientes — princípio II da
| constituição exige isolamento desde a primeira PR, sem fallback por
| header `X-Tenant` ou query param.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Sufixo de domínio público
    |--------------------------------------------------------------------------
    |
    | Domínio raiz onde os subdomínios de tenant vivem. Em prod este valor
    | DEVE vir do .env (`TENANCY_SUBDOMAIN_SUFFIX=crm.com.br`).
    |
    */

    'subdomain_suffix' => env('TENANCY_SUBDOMAIN_SUFFIX', 'lvh.me'),

    /*
    |--------------------------------------------------------------------------
    | Hosts públicos (sem tenant)
    |--------------------------------------------------------------------------
    |
    | Subdomínios que NÃO carregam tenant: cadastro público, painel super
    | admin, webhooks externos. O middleware `ResolveTenant` os ignora,
    | seguindo direto para a rota.
    |
    */

    'public_hosts' => [
        'crm',
        'admin',
        'www',
    ],

    /*
    |--------------------------------------------------------------------------
    | Slugs reservados
    |--------------------------------------------------------------------------
    |
    | Slugs que NÃO podem ser usados em registro de novo tenant — colidiriam
    | com hosts públicos, rotas internas (`/admin`, `/api`, `/panel`) ou
    | endpoints de integração (`/stripe/webhook`).
    |
    */

    'reserved_slugs' => [
        'admin',
        'api',
        'app',
        'auth',
        'cdn',
        'crm',
        'help',
        'mail',
        'panel',
        'paciente360',
        'public',
        'static',
        'status',
        'stripe',
        'support',
        'webhook',
        'webhooks',
        'www',
    ],

    /*
    |--------------------------------------------------------------------------
    | Prefixo de chaves Redis por tenant
    |--------------------------------------------------------------------------
    |
    | Princípio II — caches, locks e filas prefixados/segmentados por
    | tenant. O placeholder `{id}` é substituído pelo `tenant_id` ativo
    | no momento da chamada (ver `RedisManager` na fase de bootstrap).
    |
    */

    'redis_prefix' => 'tenant:{id}:',

    /*
    |--------------------------------------------------------------------------
    | Header de correlação
    |--------------------------------------------------------------------------
    |
    | Header propagado pelo middleware de logging estruturado (Princípio V).
    | Para fins de auditoria — NÃO confiável para resolução de tenant
    | (tenant resolvido SEMPRE pelo subdomínio do request).
    |
    */

    'correlation_header' => 'X-Correlation-Id',

];

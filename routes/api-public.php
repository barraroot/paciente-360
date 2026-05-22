<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Pública v1 — Fase 8 (T008)
|--------------------------------------------------------------------------
|
| Endpoints expostos a integradores externos via token Sanctum (ou OAuth
| Client Credentials para enterprise — Q18). Tenant resolvido **pelo token**,
| nunca por header `X-Tenant-Slug` ou parâmetro de URL — Princípio II (FR-011).
|
| Escopo (Q14) — recursos expostos em v1:
|   - patients          (RW)
|   - appointments      (RW)
|   - messages          (R)
|   - prescriptions     (R, controladas SEMPRE mascaradas)
|   - appointment-types (R)
|   - professionals     (R)
|
| Recursos fora do escopo retornam 404 (não 401) para não vazar existência.
|
| Middlewares aplicados em grupo (configurados no bootstrap/app.php):
|   - auth:sanctum (ou auth:passport quando OAuth habilitado)
|   - EnsureTenantNotSuspended (503 tenant_suspended)
|   - ApiPublicRateLimiter (por token+plano + cap IP)
|
| Endpoints serão preenchidos no Lote D (T223-T230).
|
*/

Route::prefix('v1')->group(function (): void {
    // Lote D — Controllers a serem implementados em T223-T228.
    // Placeholder vazio garante que o roteamento está registrado mesmo antes
    // da implementação dos controllers (rota inexistente retorna 404 nativo).
});

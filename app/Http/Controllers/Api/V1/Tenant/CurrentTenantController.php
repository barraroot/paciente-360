<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;

/**
 * Controller GET `/tenant` — leitura do tenant ativo (US-1.1 — T144).
 *
 * Resolvido pelo subdomínio via middleware `ResolveTenant` antes de chegar
 * aqui. Sem auth — usado pelo SPA na inicialização para carregar branding,
 * status do trial e plano vigente antes de qualquer login.
 *
 * 404 implícito: subdomínio inexistente já é abortado pelo `ResolveTenant`
 * (não chega no controller).
 *
 * @group Tenant
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § /tenant
 * @see App\Http\Middleware\ResolveTenant
 */
final class CurrentTenantController extends Controller
{
    /**
     * Leitura do tenant atual.
     *
     * Retorna dados públicos do tenant ativo (branding, status de trial,
     * plano vigente). Usado pelo SPA na inicialização.
     *
     * @unauthenticated
     *
     * @response 200 scenario="tenant ativo" {"id": 1, "slug": "clinica-alfa", "status": "trial"}
     * @response 404 scenario="subdomínio inválido" {"message": "Tenant não encontrado."}
     */
    public function __invoke(): TenantResource
    {
        /** @var Tenant $tenant */
        $tenant = app('tenant');

        $tenant->loadMissing('plan');

        return TenantResource::make($tenant);
    }
}

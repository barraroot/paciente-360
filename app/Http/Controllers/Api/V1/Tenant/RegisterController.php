<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegisterTenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Services\Tenant\TenantRegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * Controller do cadastro público de tenant (US-1.1 — T143).
 *
 * Responsabilidade única: delegar ao Service e montar a response 201
 * com `tenant`, `admin_user` e `login_url`.
 *
 * Servido APENAS no host público (`crm.lvh.me` em dev, `crm.com.br` em prod) —
 * o middleware `ResolveTenant` ignora `public_hosts` e segue direto.
 *
 * @group Tenant
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § /tenants/register
 */
final class RegisterController extends Controller
{
    /**
     * Cadastro público de nova clínica (tenant).
     *
     * Cria o tenant em estado `trial`, o usuário criador com perfil
     * Admin Clínica e envia e-mail de boas-vindas.
     *
     * @unauthenticated
     *
     * @response 201 scenario="tenant criado" {"tenant": {"id": 1, "slug": "clinica-alfa"}, "login_url": "https://clinica-alfa.crm.com.br/login"}
     */
    public function __invoke(
        RegisterTenantRequest $request,
        TenantRegistrationService $service,
    ): JsonResponse {
        $result = $service->register($request);

        $tenant = $result['tenant'];
        $loginUrl = sprintf(
            'http://%s.%s/login',
            $tenant->slug,
            (string) config('tenancy.subdomain_suffix', 'crm.com.br'),
        );

        return response()->json([
            'tenant' => TenantResource::make($tenant)->resolve(),
            'admin_user' => UserResource::make($result['admin_user'])->resolve(),
            'login_url' => $loginUrl,
        ], 201);
    }
}

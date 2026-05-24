<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Professionals;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * **T056 (Spec 012)** — POST /api/v1/professionals/check-email
 *
 * Pré-verifica se email informado no formulário de cadastro já pertence a
 * User do tenant (Q2 / R6). NÃO retorna o email do user existente
 * (Princípio I — minimização de PII).
 */
final class CheckEmailController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('professional.manage');

        $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = mb_strtolower(trim($request->string('email')->value()));
        $tenantId = $request->user()?->tenant_id;

        $userInCurrentTenant = User::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $existsInOtherTenant = User::where('tenant_id', '!=', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        return response()->json([
            'exists_in_current_tenant' => $userInCurrentTenant !== null,
            'existing_user' => $userInCurrentTenant
                ? ['id' => $userInCurrentTenant->id, 'name' => $userInCurrentTenant->name]
                : null,
            'exists_in_other_tenant' => $existsInOtherTenant,
        ]);
    }
}

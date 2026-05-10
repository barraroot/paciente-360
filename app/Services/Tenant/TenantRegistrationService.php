<?php

namespace App\Services\Tenant;

use App\Events\Tenant\TenantRegistered;
use App\Http\Requests\Tenant\RegisterTenantRequest;
use App\Jobs\Email\SendWelcomeEmailJob;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Serviço de cadastro público de tenant (US-1.1 — T143).
 *
 * Pipeline canônico (Form Request → Controller → Service → Resource):
 *  1. Resolve slug (gera a partir de `clinic_name` ou usa o enviado).
 *  2. Cria `Tenant` em `status='trial'` com `trial_ends_at = now+14d`.
 *  3. Clona as 5 roles template (`admin-clinica`, `medico`, `atendente`,
 *     `recepcionista`, `financeiro`) para `tenant_id` recém-criado,
 *     copiando todas as permissions do respectivo template.
 *  4. Cria User Admin Clínica com `email_verified_at = now()` (aceite
 *     dos Termos de Uso atua como verificação implícita do e-mail —
 *     o cadastrante prova posse via fluxo de recuperação de senha
 *     se houver erro de digitação).
 *  5. Atribui role `admin-clinica` ao usuário NO CONTEXTO do tenant
 *     (Spatie team mode: `setPermissionsTeamId($tenant->id)`).
 *  6. Despacha `SendWelcomeEmailJob` (queue — TenantAwareJob).
 *  7. Emite `TenantRegistered` (Auditable → audit_logs).
 *
 * Toda operação roda dentro de `DB::transaction` — falha em qualquer
 * etapa rola tudo (Princípio I — consistência LGPD).
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § /tenants/register
 */
final class TenantRegistrationService
{
    private const ROLE_TEMPLATES = [
        'admin-clinica',
        'medico',
        'atendente',
        'recepcionista',
        'financeiro',
    ];

    private const ADMIN_ROLE = 'admin-clinica';

    private const TRIAL_DAYS = 14;

    /**
     * Registra um novo tenant + Admin Clínica + envia welcome email.
     *
     * @return array{tenant: Tenant, admin_user: User}
     */
    public function register(RegisterTenantRequest $request): array
    {
        /** @var array{tenant: Tenant, admin_user: User} $result */
        $result = DB::transaction(function () use ($request): array {
            $slug = $this->resolveSlug(
                $request->input('slug'),
                (string) $request->input('clinic_name'),
            );

            $tenant = Tenant::query()->create([
                'slug' => $slug,
                'name' => $request->string('clinic_name')->trim()->toString(),
                'cnpj' => (string) $request->input('cnpj'),
                'responsible_name' => $request->string('responsible_name')->trim()->toString(),
                'responsible_email' => mb_strtolower($request->string('responsible_email')->trim()->toString()),
                'responsible_phone' => $request->string('responsible_phone')->trim()->toString(),
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'plan_id' => null,
                'terms_accepted_at' => now(),
                'terms_version' => $request->string('terms_version')->trim()->toString(),
                'onboarding_state' => '{}',
            ]);

            $this->cloneRoleTemplates($tenant);

            $user = $this->createAdminUser($tenant, $request);

            return ['tenant' => $tenant, 'admin_user' => $user];
        });

        // Side-effects fora da transação — não bloqueiam rollback se DB falha.
        Event::dispatch(new TenantRegistered($result['tenant'], $result['admin_user']));

        // O Job exige `app('tenant')` resolvido no publisher (TenantAwareJob).
        // Como o cadastro público roda em host sem tenant, injetamos o tenant
        // recém-criado no container apenas para construir o job.
        app()->instance('tenant', $result['tenant']);
        SendWelcomeEmailJob::dispatch($result['admin_user']->id);

        return $result;
    }

    /**
     * Resolve o slug final do tenant. Quando o cliente fornece um slug
     * válido (já validado pelo Form Request), usa direto; caso contrário,
     * gera a partir do `clinic_name` aplicando sufixo numérico em caso de
     * colisão.
     */
    private function resolveSlug(?string $providedSlug, string $clinicName): string
    {
        if (is_string($providedSlug) && $providedSlug !== '') {
            // Form Request já garantiu unicidade + formato.
            return $providedSlug;
        }

        $base = $this->normalizeSlug($clinicName);

        if ($base === '') {
            throw new RuntimeException('Não foi possível gerar slug a partir de clinic_name.');
        }

        return $this->findUniqueSlug($base);
    }

    /**
     * Normaliza um nome de clínica para slug RFC 1035 — lowercase ASCII +
     * hifens; trunca em 60 chars para deixar margem ao sufixo numérico
     * (`-99`).
     */
    private function normalizeSlug(string $name): string
    {
        $slug = Str::slug($name, '-', 'pt');
        $slug = trim($slug, '-');

        if (strlen($slug) > 60) {
            $slug = rtrim(substr($slug, 0, 60), '-');
        }

        return $slug;
    }

    /**
     * Encontra um slug livre, anexando sufixos numéricos `-2`, `-3`, ...
     * em caso de colisão. Também respeita reserved_slugs/public_hosts.
     */
    private function findUniqueSlug(string $base): string
    {
        $reserved = array_unique(array_merge(
            (array) config('tenancy.reserved_slugs'),
            (array) config('tenancy.public_hosts'),
        ));

        $candidate = $base;
        $suffix = 1;

        while (
            in_array($candidate, $reserved, true)
            || Tenant::query()->where('slug', $candidate)->exists()
        ) {
            $suffix++;
            $candidate = $base.'-'.$suffix;

            if ($suffix > 1000) {
                throw new RuntimeException('Não foi possível encontrar slug livre após 1000 tentativas.');
            }
        }

        return $candidate;
    }

    /**
     * Clona as 5 roles template (`tenant_id = NULL`) para o novo tenant,
     * copiando as permissions associadas. Permissions são templates globais
     * (`tenant_id = NULL`) compartilhados — não duplicamos linhas em
     * `permissions`, apenas o pivot `role_has_permissions` por role nova.
     */
    private function cloneRoleTemplates(Tenant $tenant): void
    {
        // Garante que firstOrCreate não filtre pelo team id atual (que pode
        // ser null em request público sem ResolveTenant).
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_TEMPLATES as $roleName) {
            $template = Role::query()
                ->where('name', $roleName)
                ->whereNull('tenant_id')
                ->first();

            if ($template === null) {
                throw new RuntimeException(
                    "Role template [{$roleName}] não encontrada — execute RolesSeeder."
                );
            }

            /** @var Role $newRole */
            $newRole = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);

            $newRole->syncPermissions($template->permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Cria o User Admin Clínica do novo tenant e atribui a role
     * `admin-clinica` no contexto do team id correto.
     */
    private function createAdminUser(Tenant $tenant, RegisterTenantRequest $request): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $request->string('responsible_name')->trim()->toString(),
            'email' => mb_strtolower($request->string('responsible_email')->trim()->toString()),
            'password' => Hash::make((string) $request->input('password')),
            'status' => 'active',
            // Aceite dos Termos é uma "verificação" implícita do e-mail —
            // o cadastrante assumiu posse e responsabilidade legal pela
            // conta. Erros de digitação são corrigíveis via fluxo de
            // recuperação de senha (que envia e-mail + valida posse).
            'email_verified_at' => now(),
            'failed_login_attempts' => 0,
        ]);

        // Spatie team mode: garante que `assignRole` atribua a role do
        // novo tenant (não a role template global de mesmo nome).
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->assignRole(self::ADMIN_ROLE);

        return $user;
    }
}

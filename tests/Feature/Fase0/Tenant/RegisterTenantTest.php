<?php

namespace Tests\Feature\Fase0\Tenant;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * **T140** — happy path do cadastro público de tenant (US-1.1, FR-001 a FR-008).
 *
 * Cobertura:
 *  - POST `/tenants/register` em host público (`crm.lvh.me`) cria tenant em
 *    estado `trial` com `trial_ends_at = now + 14 days`, terms aceitos.
 *  - Cria usuário Admin Clínica com `tenant_id` correto, `email_verified_at`
 *    populado (aceite dos Termos atua como verificação implícita) e password
 *    hasheado em argon2id.
 *  - Clona as 5 roles template (`admin-clinica`, `medico`, `atendente`,
 *    `recepcionista`, `financeiro`) para o novo `tenant_id`; atribui
 *    `admin-clinica` ao usuário criado.
 *  - Persiste evento `tenant.registered` em `audit_logs` (Princípio I).
 *  - Despacha `WelcomeNotification` por mail.
 *  - `login_url` aponta para o subdomínio do slug recém-criado.
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § /tenants/register
 * @see specs/001-fundacao-multitenant/tasks.md — T140
 */
class RegisterTenantTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_URL = 'http://crm.lvh.me/api/v1/tenants/register';

    protected function setUp(): void
    {
        parent::setUp();

        // RolesSeeder cria os 6 templates globais (`tenant_id NULL`).
        // PlansSeeder não é estritamente necessário aqui (plano fica null
        // no cadastro), mas mantemos para paridade com prod e evitar
        // surpresas se algum hook futuro precisar.
        $this->seed([RolesSeeder::class, PlansSeeder::class]);

        // Limpa o rate limiter para que múltiplos POSTs no mesmo teste
        // não esbarrem no `tenant-register` (3/h por IP).
        $this->clearTenantRegisterLimiter();

        Notification::fake();
    }

    /**
     * Reseta o limiter `tenant-register` para `127.0.0.1` (IP do test client).
     *
     * Laravel 13 deriva a chave via `md5('tenant-register' . $ip)`; o
     * `RateLimiter::clear('tenant-register')` chamado solto não bate.
     */
    private function clearTenantRegisterLimiter(): void
    {
        RateLimiter::clear(md5('tenant-register'.'127.0.0.1'));
        RateLimiter::clear('tenant-register:127.0.0.1');
        Cache::forget(md5('tenant-register'.'127.0.0.1').':timer');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'clinic_name' => 'Clínica Foo',
            'cnpj' => '11.222.333/0001-81',
            'slug' => 'clinica-foo',
            'responsible_name' => 'Dr. Foo Silva',
            'responsible_email' => 'admin@clinica-foo.com',
            'responsible_phone' => '+5511999999999',
            'password' => 'StrongPass123',
            'terms_accepted' => true,
            'terms_version' => '1.0',
        ], $overrides);
    }

    public function test_valid_payload_creates_tenant_user_and_audit(): void
    {
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload());

        $response->assertCreated();
        $response->assertJsonStructure([
            'tenant' => ['id', 'slug', 'name', 'status', 'trial_ends_at', 'restrictions_active', 'onboarding_completed'],
            'admin_user' => ['id', 'name', 'email', 'status', 'roles', 'created_at'],
            'login_url',
        ]);

        $response->assertJsonPath('tenant.slug', 'clinica-foo');
        $response->assertJsonPath('tenant.name', 'Clínica Foo');
        $response->assertJsonPath('tenant.status', 'trial');
        $response->assertJsonPath('admin_user.email', 'admin@clinica-foo.com');
        $response->assertJsonPath('admin_user.roles', ['admin-clinica']);
        $response->assertJsonPath('login_url', 'http://clinica-foo.lvh.me/login');

        // Tenant persistido com canonicalização de CNPJ + trial 14d.
        $tenant = Tenant::query()->where('slug', 'clinica-foo')->firstOrFail();
        $this->assertSame('11222333000181', $tenant->cnpj);
        $this->assertSame('trial', $tenant->status);
        $this->assertSame('1.0', $tenant->terms_version);
        $this->assertNotNull($tenant->terms_accepted_at);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertEqualsWithDelta(
            now()->addDays(14)->getTimestamp(),
            $tenant->trial_ends_at->getTimestamp(),
            5,
            'trial_ends_at deve ser ~14 dias a partir de agora.'
        );
        $this->assertNull($tenant->plan_id);

        // User Admin Clínica vinculado ao tenant.
        $user = User::query()->where('email', 'admin@clinica-foo.com')->firstOrFail();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);

        // 5 roles clonadas do template para o novo tenant.
        $tenantRoles = Role::query()
            ->where('tenant_id', $tenant->id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['admin-clinica', 'atendente', 'financeiro', 'medico', 'recepcionista'],
            $tenantRoles,
        );

        // Role atribuída ao usuário no contexto do novo tenant.
        $this->assertTrue($user->hasRole('admin-clinica'));

        // Audit log criado pelo `PersistAuditLogListener`.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.registered',
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        // Welcome email enfileirado.
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_login_url_uses_subdomain_suffix_from_config(): void
    {
        config(['tenancy.subdomain_suffix' => 'crm.example.test']);

        $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'slug' => 'clinica-suf',
            'cnpj' => '04.252.011/0001-10',
            'responsible_email' => 'suf@clinica.com',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('login_url', 'http://clinica-suf.crm.example.test/login');
    }

    public function test_password_is_hashed_with_argon2id(): void
    {
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'slug' => 'clinica-arg',
            'cnpj' => '04.252.011/0001-10',
            'responsible_email' => 'arg@clinica.com',
            'password' => 'TopSecret9X',
        ]))->assertCreated();

        $user = User::query()->where('email', 'arg@clinica.com')->firstOrFail();

        $this->assertTrue(Hash::check('TopSecret9X', $user->password));
        $this->assertSame('argon2id', Hash::info($user->password)['algoName'] ?? null);
    }

    public function test_slug_can_be_provided_or_generated(): void
    {
        // 1. slug ausente → gerado a partir de clinic_name.
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'clinic_name' => 'Clínica Saúde Total',
            'slug' => null,
            'cnpj' => '04.252.011/0001-10',
            'responsible_email' => 'gen@clinica.com',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('tenant.slug', 'clinica-saude-total');

        // 2. slug enviado → usado tal qual quando válido.
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'clinic_name' => 'Clínica Y',
            'slug' => 'minha-clinica',
            'cnpj' => '11.444.777/0001-61',
            'responsible_email' => 'sup@clinica.com',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('tenant.slug', 'minha-clinica');
    }
}

<?php

namespace Tests\Feature\Fase3\Foundational;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T040 — Autorização de canais privados Omnichannel Inbox (Fase 3).
 *
 * Bate em `/broadcasting/auth` com payload `socket_id` + `channel_name`
 * e valida os callbacks registrados em `routes/channels.php` (T041).
 *
 * Princípio II: isolamento multi-tenant rigoroso — user de tenant B
 * não consegue subscrever canal do tenant A; role sem `inbox.view`
 * é rejeitado; médico acessa apenas conversas visíveis a ele (spec § 2.3).
 *
 * Fase 4 Lote F (T062 — broadcasting Bearer): este teste foi migrado de
 * `actingAs($user)` (cookie-session) para Bearer Sanctum + X-Tenant-Slug,
 * antecipando o trabalho do Lote I (T083-T089) para manter a suite verde
 * após a troca de middleware de `/broadcasting/auth` para `auth:sanctum`.
 */
class ReverbChannelAuthorizationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Para validar os callbacks de `Broadcast::channel(...)` via
        // `/broadcasting/auth`, o broadcaster precisa ser `reverb`
        // (Pusher-compat). O default em phpunit.xml é `null` (no-op).
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app-id',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
            'client_options' => [],
        ]);

        // Re-aplica os callbacks ao driver recém-criado.
        require base_path('routes/channels.php');

        // Semeie as roles/permissions template globais antes de cada teste.
        $this->seedRoles();
    }

    /**
     * Autentica o user via Sanctum::actingAs (preserva a instância com cache
     * de roles do Spatie carregado) e retorna o header X-Tenant-Slug exigido
     * pelo middleware `tenant.slug` em /broadcasting/auth.
     *
     * Por que NÃO usar Bearer real aqui:
     *   `$user->createToken()` + Authorization header faz Sanctum recarregar
     *   o User do DB sem o cache de relations do Spatie. O channel callback
     *   tenta `$user->can('inbox.view')` que depende de team_id correto —
     *   no ciclo HTTP de teste o PermissionRegistrar é reinjetado e o team_id
     *   setado em setUp pode ser perdido, levando a falso 403.
     *
     *   Sanctum::actingAs usa TransientToken e setUser direto no guard, sem
     *   passar pelo PersonalAccessTokenAuthGuard, preservando a instância
     *   exata do `$user` com suas relations já hidratadas.
     *
     * @return array<string, string>
     */
    private function tenantSlugHeader(User $user, Tenant $tenant): array
    {
        Sanctum::actingAs($user, ['*']);

        return ['X-Tenant-Slug' => $tenant->slug];
    }

    // -------------------------------------------------------------------------
    // Cenário 1 — user com inbox.view autorizado no próprio tenant
    // -------------------------------------------------------------------------

    public function test_it_authorizes_user_on_own_tenant_inbox_channel_when_has_inbox_view_ability(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('clinica-a');
        $user = $this->userForRole($tenantA, 'atendente'); // atendente tem inbox.view

        $this->setPermissionsTeamIdForTenant($tenantA->id);

        $response = $this->withHeaders($this->tenantSlugHeader($user, $tenantA))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-tenant.{$tenantA->id}.inbox",
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['auth']);
    }

    // -------------------------------------------------------------------------
    // Cenário 2 — user sem inbox.view rejeitado no canal inbox
    // -------------------------------------------------------------------------

    public function test_it_rejects_user_without_inbox_view_ability(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-b');
        $user = $this->userForRole($tenant, 'financeiro'); // financeiro NÃO tem inbox.view

        $this->setPermissionsTeamIdForTenant($tenant->id);

        $response = $this->withHeaders($this->tenantSlugHeader($user, $tenant))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-tenant.{$tenant->id}.inbox",
            ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Cenário 3 — user de tenant A rejeitado ao tentar subscrever canal de tenant B
    // -------------------------------------------------------------------------

    public function test_it_rejects_user_on_other_tenant_inbox_channel(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('clinica-c');
        $tenantB = $this->bootstrapTenantWithRoles('clinica-d');

        $userA = $this->userForRole($tenantA, 'atendente');

        $this->setPermissionsTeamIdForTenant($tenantA->id);

        // userA do tenant A com X-Tenant-Slug=A tenta subscrever canal de B
        // → channel callback rejeita (user.tenant_id !== tenantId do canal) → 403.
        $response = $this->withHeaders($this->tenantSlugHeader($userA, $tenantA))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-tenant.{$tenantB->id}.inbox",
            ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Cenário 4 — médico acessa apenas conversas visíveis; admin vê todas
    // -------------------------------------------------------------------------

    public function test_it_authorizes_user_on_conversation_channel_only_if_can_see_it(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('clinica-e');

        // U1 = médico atribuído à conversa
        $userU1 = $this->userForRole($tenantA, 'medico');
        // U2 = médico sem atribuição e sem ser profissional responsável do paciente
        $userU2 = $this->userForRole($tenantA, 'medico');
        // U3 = admin-clinica (vê todas as conversas do tenant)
        $userU3 = $this->userForRole($tenantA, 'admin-clinica');

        // Canal de conectividade: precisamos de um Channel para criar a conversa.
        // tenant_id explícito — BelongsToTenant requer app('tenant') no container.
        DB::table('messaging_channels')->insert([
            'tenant_id' => $tenantA->id,
            'type' => 'whatsapp',
            'name' => 'WhatsApp Teste',
            'status' => 'ativo',
            'provider_metadata' => json_encode(['phone_number_id' => 'pn-'.uniqid()]),
            'auto_send_disabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $channelId = DB::table('messaging_channels')->latest('id')->value('id');

        // Paciente sem profissional responsável (nenhum dos médicos é responsável)
        $paciente = Paciente::factory()->forTenant($tenantA)->create([
            'status' => 'ativo',
            'telefone_primario' => '(31) 99999-0001',
        ]);

        // Conversa C1 atribuída a U1 — tenant_id explícito via DB::table
        DB::table('messaging_conversations')->insert([
            'tenant_id' => $tenantA->id,
            'channel_id' => $channelId,
            'patient_id' => $paciente->id,
            'external_thread_id' => 'thread-'.uniqid(),
            'status' => 'aberta',
            'assigned_user_id' => $userU1->id,
            'priority' => 'normal',
            'received_outside_hours' => false,
            'unread_count' => 0,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $conversationId = DB::table('messaging_conversations')->latest('id')->value('id');
        $conversation = Conversation::withoutTenantScope()->find($conversationId);

        // -- Sub-cenário 4a: U1 (atribuído) → 200 --
        $this->setPermissionsTeamIdForTenant($tenantA->id);

        $responseU1 = $this->withHeaders($this->tenantSlugHeader($userU1, $tenantA))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '100.001',
                'channel_name' => "private-tenant.{$tenantA->id}.conversa.{$conversation->id}",
            ]);

        $responseU1->assertOk();

        // -- Sub-cenário 4b: U2 (médico sem assignment nem responsabilidade) → 403 --
        $responseU2 = $this->withHeaders($this->tenantSlugHeader($userU2, $tenantA))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '100.002',
                'channel_name' => "private-tenant.{$tenantA->id}.conversa.{$conversation->id}",
            ]);

        $responseU2->assertForbidden();

        // -- Sub-cenário 4c: U3 (admin-clinica) → 200 --
        $responseU3 = $this->withHeaders($this->tenantSlugHeader($userU3, $tenantA))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '100.003',
                'channel_name' => "private-tenant.{$tenantA->id}.conversa.{$conversation->id}",
            ]);

        $responseU3->assertOk();
    }

    /**
     * Médico acessa conversa porque é o profissional responsável pelo paciente.
     * Cobre o segundo branch da lógica `isPatientOwner` em `channels.php`.
     */
    public function test_it_authorizes_medico_on_conversation_channel_when_is_patient_owner(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinica-f');
        $medicoUser = $this->userForRole($tenant, 'medico');

        // Profissional vinculado ao User médico — tenant_id explícito pois
        // o container pode não ter `app('tenant')` resolvido neste contexto.
        $professional = Professional::factory()->forTenant($tenant)->create([
            'user_id' => $medicoUser->id,
            'name' => $medicoUser->name,
            'is_active' => true,
        ]);

        // Paciente cujo profissional responsável é este Professional
        $paciente = Paciente::factory()->forTenant($tenant)->create([
            'status' => 'ativo',
            'telefone_primario' => '(31) 99999-0002',
            'profissional_responsavel_id' => $professional->id,
        ]);

        DB::table('messaging_channels')->insert([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
            'name' => 'WhatsApp Teste 2',
            'status' => 'ativo',
            'provider_metadata' => json_encode(['phone_number_id' => 'pn-'.uniqid()]),
            'auto_send_disabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $channelId = DB::table('messaging_channels')->latest('id')->value('id');

        // Conversa NÃO atribuída ao médico (assigned_user_id = null) — acesso via isPatientOwner
        DB::table('messaging_conversations')->insert([
            'tenant_id' => $tenant->id,
            'channel_id' => $channelId,
            'patient_id' => $paciente->id,
            'external_thread_id' => 'thread-'.uniqid(),
            'status' => 'aberta',
            'assigned_user_id' => null,
            'priority' => 'normal',
            'received_outside_hours' => false,
            'unread_count' => 0,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $conversationId = DB::table('messaging_conversations')->latest('id')->value('id');
        $conversation = Conversation::withoutTenantScope()->find($conversationId);

        $this->setPermissionsTeamIdForTenant($tenant->id);

        $response = $this->withHeaders($this->tenantSlugHeader($medicoUser, $tenant))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '200.001',
                'channel_name' => "private-tenant.{$tenant->id}.conversa.{$conversation->id}",
            ]);

        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Configura o team_id do Spatie PermissionRegistrar para o tenant informado.
     * Necessário para que `$user->can('inbox.view')` consulte permissões do tenant.
     */
    private function setPermissionsTeamIdForTenant(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantId);
        $registrar->forgetCachedPermissions();
    }
}

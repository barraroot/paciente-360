<?php

namespace Tests\Feature\Fase3\US2_Instagram;

use App\Domain\Messaging\Channel\Adapters\InstagramGraphAdapter;
use App\Domain\Messaging\Channel\Events\CanalConectado;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T184 — Feature tests para POST /api/v1/inbox/channels com type=instagram.
 *
 * Cobre AC-4.2.1 (conexão com conta Business), AC-4.2.2 (rejeição de conta
 * pessoal) e proteção cross-tenant (Princípio II).
 *
 * Usa Http::fake() para simular Graph API sem HTTP real.
 */
class ConnectInstagramTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private string $igBusinessAccountId = '123456789012345';

    private string $pageId = '98765432109876';

    private string $pageAccessToken = 'EAABsbCS-fake-long-lived-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-instagram', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /**
     * Mocka o InstagramGraphAdapter no container para simular conta Business.
     * Necessário porque o adapter usa Guzzle diretamente (não Http facade).
     */
    private function fakeGraphApiBusinessAccount(string $username = 'clinica_alfa'): void
    {
        $mock = Mockery::mock(InstagramGraphAdapter::class);
        $mock->shouldReceive('getType')->andReturn('instagram');
        $mock->shouldReceive('validateCredentials')->andReturn(true);
        $mock->shouldReceive('fetchIgUsername')->andReturn($username);
        $this->app->instance(InstagramGraphAdapter::class, $mock);
    }

    /**
     * Mocka o adapter para simular conta PERSONAL (rejeição).
     */
    private function fakeGraphApiPersonalAccount(): void
    {
        $mock = Mockery::mock(InstagramGraphAdapter::class);
        $mock->shouldReceive('getType')->andReturn('instagram');
        $mock->shouldReceive('validateCredentials')->andReturn(false);
        $mock->shouldReceive('fetchIgUsername')->andReturn(null);
        $this->app->instance(InstagramGraphAdapter::class, $mock);
    }

    /**
     * Mocka o adapter para simular token inválido (401).
     */
    private function fakeGraphApiInvalidToken(): void
    {
        $mock = Mockery::mock(InstagramGraphAdapter::class);
        $mock->shouldReceive('getType')->andReturn('instagram');
        $mock->shouldReceive('validateCredentials')->andReturn(false);
        $mock->shouldReceive('fetchIgUsername')->andReturn(null);
        $this->app->instance(InstagramGraphAdapter::class, $mock);
    }

    /** @test */
    public function test_admin_clinica_can_connect_instagram_with_business_account_credentials(): void
    {
        $this->fakeGraphApiBusinessAccount('clinica_alfa');

        $payload = [
            'type' => 'instagram',
            'name' => 'Instagram Clínica Alfa',
            'credentials' => [
                'page_id' => $this->pageId,
                'page_access_token' => $this->pageAccessToken,
                'ig_business_account_id' => $this->igBusinessAccountId,
            ],
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertCreated();

        // Verifica persistência do canal com type=instagram
        $this->assertDatabaseHas('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'instagram',
            'name' => 'Instagram Clínica Alfa',
            'status' => 'ativo',
        ]);

        // Verifica provider_metadata com campos públicos (sem secrets)
        $channel = Channel::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('type', 'instagram')
            ->first();

        $this->assertNotNull($channel);
        $this->assertEquals($this->pageId, $channel->provider_metadata['page_id'] ?? null);
        $this->assertEquals($this->igBusinessAccountId, $channel->provider_metadata['ig_business_account_id'] ?? null);
        $this->assertEquals('clinica_alfa', $channel->provider_metadata['ig_username'] ?? null);

        // Page access token NÃO deve aparecer em provider_metadata (é secret)
        $this->assertArrayNotHasKey('page_access_token', $channel->provider_metadata ?? []);

        // Auditoria channel.connected (AC-4.2.1)
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'channel.connected',
            'executor_id' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function test_connect_instagram_rejects_personal_account_returns_422(): void
    {
        // Conta pessoal: account_type=PERSONAL — adapter retorna false (AC-4.2.2)
        $this->fakeGraphApiPersonalAccount();

        $payload = [
            'type' => 'instagram',
            'name' => 'Instagram Pessoal',
            'credentials' => [
                'page_id' => $this->pageId,
                'page_access_token' => $this->pageAccessToken,
                'ig_business_account_id' => $this->igBusinessAccountId,
            ],
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertUnprocessable();

        // Canal NÃO deve ser criado
        $this->assertDatabaseMissing('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'instagram',
        ]);
    }

    /** @test */
    public function test_connect_instagram_rejects_invalid_token_returns_422(): void
    {
        // Token inválido: Graph API retorna 401 — adapter retorna false
        $this->fakeGraphApiInvalidToken();

        $payload = [
            'type' => 'instagram',
            'name' => 'Instagram Token Inválido',
            'credentials' => [
                'page_id' => $this->pageId,
                'page_access_token' => 'invalid-token',
                'ig_business_account_id' => $this->igBusinessAccountId,
            ],
        ];

        $response = $this->postJson($this->baseUrl('/inbox/channels'), $payload);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('messaging_channels', [
            'tenant_id' => $this->tenant->id,
            'type' => 'instagram',
        ]);
    }

    /** @test */
    public function test_connect_fires_canal_conectado_event_with_executor_id(): void
    {
        $this->fakeGraphApiBusinessAccount();
        Event::fake([CanalConectado::class]);

        $payload = [
            'type' => 'instagram',
            'name' => 'Instagram com Evento',
            'credentials' => [
                'page_id' => $this->pageId,
                'page_access_token' => $this->pageAccessToken,
                'ig_business_account_id' => $this->igBusinessAccountId,
            ],
        ];

        $this->postJson($this->baseUrl('/inbox/channels'), $payload)->assertCreated();

        Event::assertDispatched(CanalConectado::class, function (CanalConectado $event) {
            return $event->executorId === $this->adminUser->id;
        });
    }

    /** @test */
    public function test_cross_tenant_protection_instagram_channel_not_visible_to_other_tenant(): void
    {
        $this->fakeGraphApiBusinessAccount();

        // Tenant A conecta Instagram
        $payload = [
            'type' => 'instagram',
            'name' => 'Instagram Tenant A',
            'credentials' => [
                'page_id' => $this->pageId,
                'page_access_token' => $this->pageAccessToken,
                'ig_business_account_id' => $this->igBusinessAccountId,
            ],
        ];

        $this->postJson($this->baseUrl('/inbox/channels'), $payload)->assertCreated();

        // Tenant B tenta listar canais — NÃO deve ver o canal do tenant A
        [$tenantB, $userB] = $this->tenantAndUserForRole('clinica-instagram-b', 'admin-clinica');
        $this->app->instance('tenant', $tenantB);
        Sanctum::actingAs($userB, ['*']);

        $response = $this->getJson($this->tenantUrl($tenantB, '/inbox/channels'));
        $response->assertOk();

        $channelIds = collect($response->json('data'))->pluck('id')->all();
        $tenantAChannel = Channel::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('type', 'instagram')
            ->first();

        $this->assertNotNull($tenantAChannel);
        $this->assertNotContains($tenantAChannel->id, $channelIds);
    }
}

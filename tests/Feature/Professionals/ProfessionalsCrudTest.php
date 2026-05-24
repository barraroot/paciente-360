<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T010 / T029 / T043 (Spec 012)** — CRUD interno de Professional pelo painel.
 *
 * Cobre US-2 (cadastro/listagem), US-3 (edição + campos imutáveis) e US-6
 * (filtros/busca/paginação). Não duplica gates dedicados (cross-tenant,
 * permission, uniqueness, invite, autocomplete) que vivem em arquivos próprios.
 */
final class ProfessionalsCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenant] = $this->tenantAndUserForRole('crud-admin', 'admin-clinica');
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_admin_can_create_professional_linked_to_existing_user(): void
    {
        $linkedUser = $this->createUserForTenant($this->tenant);

        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/professionals'),
            [
                'name' => 'Dr. Carlos Santos',
                'council_type' => 'CRM',
                'council_number' => '123456',
                'council_state' => 'SP',
                'especialidade' => 'Cardiologia',
                'user_id' => $linkedUser->id,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.user.id', $linkedUser->id);

        $this->assertDatabaseHas('professionals', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $linkedUser->id,
            'name' => 'Dr. Carlos Santos',
            'council_state' => 'SP',
            'is_active' => true,
        ]);
    }

    public function test_resource_does_not_expose_linked_user_email(): void
    {
        $linkedUser = $this->createUserForTenant($this->tenant);
        $professional = Professional::factory()->forTenant($this->tenant)->create(['user_id' => $linkedUser->id]);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}")
        );

        $response->assertOk();
        $response->assertJsonMissingPath('data.user.email');
        $this->assertStringNotContainsString($linkedUser->email, $response->getContent());
    }

    public function test_admin_can_view_single_professional(): void
    {
        $professional = Professional::factory()->forTenant($this->tenant)->create();

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}")
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $professional->id);
    }

    public function test_admin_can_update_basic_and_council_fields(): void
    {
        $professional = Professional::factory()->forTenant($this->tenant)->create([
            'council_type' => 'CRM',
            'council_number' => '111111',
            'council_state' => 'SP',
            'especialidade' => 'Clínica Geral',
        ]);

        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}"),
            [
                'name' => 'Dr. Carlos R. Santos',
                'council_type' => 'CRM',
                'council_number' => '987654',
                'council_state' => 'RJ',
                'especialidade' => 'Cardiologia Pediátrica',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.council_number', '987654');
        $response->assertJsonPath('data.council_state', 'RJ');
        $response->assertJsonPath('data.especialidade', 'Cardiologia Pediátrica');
    }

    public function test_user_id_is_prohibited_on_update(): void
    {
        $original = $this->createUserForTenant($this->tenant);
        $other = $this->createUserForTenant($this->tenant);
        $professional = Professional::factory()->forTenant($this->tenant)->create(['user_id' => $original->id]);

        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}"),
            ['name' => 'Novo Nome', 'user_id' => $other->id]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('user_id');
        $this->assertSame($original->id, $professional->fresh()->user_id);
    }

    public function test_is_active_is_prohibited_on_update(): void
    {
        $professional = Professional::factory()->forTenant($this->tenant)->create(['is_active' => true]);

        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}"),
            ['name' => 'Novo Nome', 'is_active' => false]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_active');
    }

    public function test_list_default_filter_returns_active_only(): void
    {
        Professional::factory()->count(3)->forTenant($this->tenant)->create(['is_active' => true]);
        $inactive = Professional::factory()->forTenant($this->tenant)->create(['is_active' => false]);
        $inactive->delete();

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals')
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertCount(3, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_list_filter_inactive_returns_only_inactive(): void
    {
        Professional::factory()->count(2)->forTenant($this->tenant)->create(['is_active' => true]);
        $inactive = Professional::factory()->forTenant($this->tenant)->create(['is_active' => false]);
        $inactive->delete();

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals?is_active=false')
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$inactive->id], $ids);
    }

    public function test_list_filter_all_includes_active_and_inactive(): void
    {
        Professional::factory()->count(2)->forTenant($this->tenant)->create(['is_active' => true]);
        $inactive = Professional::factory()->forTenant($this->tenant)->create(['is_active' => false]);
        $inactive->delete();

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals?is_active=all')
        );

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_search_by_name_is_case_insensitive(): void
    {
        Professional::factory()->forTenant($this->tenant)->create(['name' => 'Dra. Maria Souza']);
        Professional::factory()->forTenant($this->tenant)->create(['name' => 'Dr. João Lima']);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals?search=maria')
        );

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Dra. Maria Souza'], $names);
    }

    public function test_cursor_pagination_returns_next_link(): void
    {
        Professional::factory()->count(30)->forTenant($this->tenant)->create(['is_active' => true]);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/professionals?per_page=10')
        );

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertNotNull($response->json('links.next'));
    }
}

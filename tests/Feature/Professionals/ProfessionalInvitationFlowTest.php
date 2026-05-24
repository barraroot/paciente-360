<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Events\Professionals\ProfessionalActivatedByInvitation;
use App\Events\Users\InvitationAccepted;
use App\Models\Invitation;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **G4 + G9 (Spec 012)** — Fluxo de convite cria Professional pendente; aceitar
 * convite ativa o profissional. Cross-tenant: email já é user em outro tenant → 422.
 */
final class ProfessionalInvitationFlowTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_create_with_email_creates_pending_professional_and_invitation(): void
    {
        [$tenant] = $this->tenantAndUserForRole('invite-flow', 'admin-clinica');
        $headers = ['X-Tenant-Slug' => $tenant->slug];

        $response = $this->withHeaders($headers)->postJson(
            $this->tenantUrl($tenant, '/professionals'),
            [
                'name' => 'Dra. Convidada',
                'council_type' => 'CRM',
                'council_number' => '13579',
                'council_state' => 'SP',
                'email' => 'nova@example.com',
            ]
        );

        $response->assertCreated();

        $professional = Professional::where('pending_invitation_email', 'nova@example.com')->first();
        $this->assertNotNull($professional);
        $this->assertFalse((bool) $professional->is_active);
        $this->assertNull($professional->user_id);
        $this->assertSame($tenant->id, $professional->tenant_id);

        $this->assertDatabaseHas('invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'nova@example.com',
            'status' => 'pending',
            'intended_role' => 'medico',
        ]);
    }

    public function test_invitation_accepted_event_activates_pending_professional(): void
    {
        [$tenant] = $this->tenantAndUserForRole('invite-accept', 'admin-clinica');
        $inviter = $this->createUserForTenant($tenant);

        $professional = Professional::factory()
            ->forTenant($tenant)
            ->create([
                'user_id' => null,
                'pending_invitation_email' => 'aceitar@example.com',
                'is_active' => false,
            ]);

        $invitation = Invitation::factory()
            ->forTenant($tenant, $inviter)
            ->create(['email' => 'aceitar@example.com']);

        $newUser = User::factory()->forTenant($tenant)->create([
            'email' => 'aceitar@example.com',
        ]);

        Event::fake([ProfessionalActivatedByInvitation::class]);

        event(new InvitationAccepted($invitation, $newUser));

        $professional->refresh();
        $this->assertTrue((bool) $professional->is_active);
        $this->assertSame($newUser->id, $professional->user_id);
        $this->assertNull($professional->pending_invitation_email);

        Event::assertDispatched(ProfessionalActivatedByInvitation::class);
    }

    public function test_email_belonging_to_other_tenant_is_blocked(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('invite-other-b');
        User::factory()->forTenant($tenantB)->create(['email' => 'existing@example.com']);

        [$tenantA] = $this->tenantAndUserForRole('invite-other-a', 'admin-clinica');

        $response = $this->withHeaders(['X-Tenant-Slug' => $tenantA->slug])->postJson(
            $this->tenantUrl($tenantA, '/professionals'),
            [
                'name' => 'Dr. Cross-Tenant',
                'council_type' => 'CRM',
                'council_number' => '24680',
                'council_state' => 'SP',
                'email' => 'existing@example.com',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseMissing('professionals', [
            'tenant_id' => $tenantA->id,
            'pending_invitation_email' => 'existing@example.com',
        ]);
    }

    public function test_email_already_in_current_tenant_returns_409(): void
    {
        [$tenant] = $this->tenantAndUserForRole('invite-409', 'admin-clinica');
        $existing = User::factory()->forTenant($tenant)->create([
            'email' => 'existente@example.com',
            'name' => 'Já Existo',
        ]);

        $response = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])->postJson(
            $this->tenantUrl($tenant, '/professionals'),
            [
                'name' => 'Dr. Existente',
                'council_type' => 'CRM',
                'council_number' => '11223',
                'council_state' => 'SP',
                'email' => 'existente@example.com',
            ]
        );

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'email_already_user_requires_confirmation');
        $response->assertJsonPath('existing_user.id', $existing->id);
    }
}

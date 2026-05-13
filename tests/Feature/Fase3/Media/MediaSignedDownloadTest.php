<?php

namespace Tests\Feature\Fase3\Media;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T248 — Feature tests para GET /api/v1/inbox/media/{id} (NC-9, download assinado).
 *
 * Cobre: URL assinada 24h, 410 para mídia purgada, isolamento de tenant.
 */
class MediaSignedDownloadTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendant;

    private Conversation $conversation;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');

        $this->seedRoles();
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-download', 'atendente');

        $channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->create(['channel_id' => $channel->id, 'status' => 'aberta']);

        $this->message = Message::factory()
            ->for($this->conversation)
            ->create([
                'tenant_id' => $this->tenant->id,
                'direction' => 'in',
            ]);
    }

    #[Test]
    public function get_media_returns_presigned_url_with_24h_expiry(): void
    {
        // Primeiro cria um arquivo falso no storage fake
        Storage::disk('media')->put('tenant_1/test.jpg', 'fake-content');

        $media = MessageMedia::factory()
            ->forTenant($this->tenant)
            ->create([
                'message_id' => $this->message->id,
                'storage_disk' => 'media',
                'storage_path' => 'tenant_1/test.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => 5000,
                'sensitive_hint' => true,
                'media_purged_at' => null,
            ]);

        $response = $this->getJson("/api/v1/inbox/media/{$media->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'download_url',
                'expires_at',
                'mime_type',
                'size_bytes',
                'sensitive_hint',
            ])
            ->assertJson([
                'mime_type' => 'image/jpeg',
                'size_bytes' => 5000,
                'sensitive_hint' => true,
            ]);

        // Verifica expiry próximo de 24h
        $expiresAt = now()->parse($response->json('expires_at'));
        $expected = now()->addMinutes(1440);
        $this->assertTrue(abs($expiresAt->diffInMinutes($expected)) < 5);
    }

    #[Test]
    public function purged_media_returns_410_with_placeholder(): void
    {
        $media = MessageMedia::factory()
            ->forTenant($this->tenant)
            ->purgada()
            ->create([
                'message_id' => $this->message->id,
            ]);

        $response = $this->getJson("/api/v1/inbox/media/{$media->id}");

        $response->assertStatus(410)
            ->assertJsonStructure(['message', 'placeholder']);
    }

    #[Test]
    public function returns_404_for_other_tenant_media(): void
    {
        [$tenantB] = $this->tenantAndUserForRole('clinica-b-download', 'atendente');

        $channelB = Channel::factory()
            ->forTenant($tenantB)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $convB = Conversation::factory()
            ->forTenant($tenantB)
            ->create(['channel_id' => $channelB->id]);

        $msgB = Message::factory()
            ->for($convB)
            ->create(['tenant_id' => $tenantB->id]);

        $mediaB = MessageMedia::factory()
            ->forTenant($tenantB)
            ->create(['message_id' => $msgB->id]);

        // Volta para o usuário A
        Sanctum::actingAs($this->attendant, ['*']);
        $this->app->instance('tenant', $this->tenant);

        $response = $this->getJson("/api/v1/inbox/media/{$mediaB->id}");

        $response->assertStatus(404);
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        auth()->forgetGuards();

        $media = MessageMedia::factory()
            ->forTenant($this->tenant)
            ->create(['message_id' => $this->message->id]);

        $response = $this->getJson("/api/v1/inbox/media/{$media->id}");

        $response->assertStatus(401);
    }
}

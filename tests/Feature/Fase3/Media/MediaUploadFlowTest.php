<?php

namespace Tests\Feature\Fase3\Media;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Services\MediaUploadService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T247 — Feature tests para POST /api/v1/inbox/media/upload (NC-9, FR-020).
 *
 * Cobre: presigned URL, MIME whitelist, size limits, extension blocklist,
 * media_token TTL e autorização por ability.
 */
class MediaUploadFlowTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendant;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');

        $this->seedRoles();
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-media', 'atendente');

        $channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->create(['channel_id' => $channel->id, 'status' => 'aberta']);
    }

    #[Test]
    public function admin_can_request_presigned_upload_url_for_image(): void
    {
        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5_000_000,
            'original_filename' => 'foto.jpg',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'media_token',
                'upload_url',
                'upload_headers',
                'expires_at',
                'media_id',
            ]);

        $this->assertNotEmpty($response->json('media_token'));
        $this->assertNotEmpty($response->json('upload_url'));
    }

    #[Test]
    public function rejects_mime_type_not_in_whitelist_422(): void
    {
        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'application/x-msdownload',
            'size_bytes' => 100,
            'original_filename' => 'virus.exe',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mime_type']);
    }

    #[Test]
    public function rejects_size_exceeds_limit_for_image_422(): void
    {
        // 16MB + 1 byte
        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 16_777_217,
            'original_filename' => 'grande.jpg',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function rejects_pdf_exceeding_100mb_limit(): void
    {
        // 100MB + 1 byte
        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'application/pdf',
            'size_bytes' => 104_857_601,
            'original_filename' => 'enorme.pdf',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function enforces_per_type_limits(): void
    {
        $cases = [
            ['image/jpeg', 16_777_216, true],
            ['image/png', 16_777_216, true],
            ['image/webp', 16_777_216, true],
            ['audio/mpeg', 16_777_216, true],
            ['audio/ogg', 16_777_216, true],
            ['audio/mp4', 16_777_216, true],
            ['video/mp4', 16_777_216, true],
            ['application/pdf', 104_857_600, true],
            // over limit
            ['image/jpeg', 16_777_217, false],
            ['application/pdf', 104_857_601, false],
        ];

        foreach ($cases as [$mime, $size, $shouldSucceed]) {
            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'audio/mpeg' => 'mp3',
                'audio/ogg' => 'ogg',
                'audio/mp4' => 'm4a',
                'video/mp4' => 'mp4',
                'application/pdf' => 'pdf',
                default => 'bin',
            };

            $response = $this->postJson('/api/v1/inbox/media/upload', [
                'conversation_id' => $this->conversation->id,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'original_filename' => "test.{$ext}",
            ]);

            if ($shouldSucceed) {
                $response->assertStatus(201, "Expected 201 for {$mime} at {$size} bytes");
            } else {
                $response->assertStatus(422, "Expected 422 for {$mime} at {$size} bytes");
            }
        }
    }

    #[Test]
    public function media_token_is_short_lived_ttl_1h(): void
    {
        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100_000,
            'original_filename' => 'foto.jpg',
        ]);

        $response->assertStatus(201);

        $expiresAt = now()->parse($response->json('expires_at'));
        $expectedExpiry = now()->addMinutes(60);

        // Verifica que o token expira em aproximadamente 1h (margem de 5s)
        $this->assertTrue(
            abs($expiresAt->diffInSeconds($expectedExpiry)) < 5,
            "Media token should expire in ~1h, got: {$response->json('expires_at')}"
        );
    }

    #[Test]
    public function rejects_blocked_extension_even_with_valid_mime(): void
    {
        // Arquivo .exe com MIME válido (trickery)
        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
            'original_filename' => 'malware.exe',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['original_filename']);
    }

    #[Test]
    public function requires_inbox_respond_ability(): void
    {
        // Usuário financeiro não tem inbox.respond
        $financeiro = $this->userForRole($this->tenant, 'financeiro');
        Sanctum::actingAs($financeiro, ['*']);

        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100_000,
            'original_filename' => 'foto.jpg',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        auth()->forgetGuards();

        $response = $this->postJson('/api/v1/inbox/media/upload', [
            'conversation_id' => $this->conversation->id,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100_000,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function whitelist_contains_all_expected_mime_types(): void
    {
        $expectedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'audio/mpeg',
            'audio/ogg',
            'audio/mp4',
            'video/mp4',
            'application/pdf',
        ];

        foreach ($expectedMimes as $mime) {
            $this->assertArrayHasKey($mime, MediaUploadService::MIME_LIMITS);
        }
    }
}

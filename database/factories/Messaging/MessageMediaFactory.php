<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * T038 — Factory para `MessageMedia`.
 *
 * @extends Factory<MessageMedia>
 */
class MessageMediaFactory extends Factory
{
    protected $model = MessageMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ext = fake()->randomElement(['jpg', 'png', 'pdf', 'mp4', 'ogg']);
        $filename = fake()->slug(2).'.'.$ext;

        $mimeMap = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'ogg' => 'audio/ogg',
        ];

        return [
            'storage_disk' => 'media',
            'storage_path' => 'tenant_1/conversa_1/msg_1/'.$filename,
            'mime_type' => $mimeMap[$ext],
            'size_bytes' => fake()->numberBetween(1024, 10 * 1024 * 1024),
            'original_filename' => $filename,
            'checksum_sha256' => hash('sha256', Str::random(64)),
            'sensitive_hint' => true,
            'media_purged_at' => null,
        ];
    }

    /**
     * Imagem JPEG.
     */
    public function image(): static
    {
        return $this->state([
            'mime_type' => 'image/jpeg',
            'original_filename' => fake()->slug(2).'.jpg',
        ]);
    }

    /**
     * Documento PDF.
     */
    public function pdf(): static
    {
        return $this->state([
            'mime_type' => 'application/pdf',
            'original_filename' => fake()->slug(2).'.pdf',
        ]);
    }

    /**
     * Áudio OGG (voz).
     */
    public function audio(): static
    {
        return $this->state([
            'mime_type' => 'audio/ogg',
            'original_filename' => fake()->slug(2).'.ogg',
        ]);
    }

    /**
     * Mídia já purgada pelo job de retenção.
     */
    public function purgada(): static
    {
        return $this->state([
            'media_purged_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}

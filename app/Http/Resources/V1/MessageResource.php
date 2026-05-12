<?php

namespace App\Http\Resources\V1;

use App\Domain\Messaging\Message\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * T118 — Resource de `Message` para API responses.
 *
 * Body é retornado desencriptado (o cast do model já decripta).
 * Media inclui download_url pre-signed com validade de 24h.
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'body_preview' => $this->body_preview,
            'content_type' => $this->content_type,
            'template_provider_id' => $this->template_provider_id,
            'template_variables' => $this->template_variables,
            'status' => $this->status,
            'idempotency_key' => $this->idempotency_key,
            'external_id' => $this->external_id,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'media' => $this->whenLoaded('media', function () {
                return $this->media->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'mime_type' => $m->mime_type,
                        'size_bytes' => $m->size_bytes,
                        'download_url' => $this->buildDownloadUrl($m),
                    ];
                });
            }),
        ];
    }

    /**
     * Gera URL pre-signed com validade de 24h.
     */
    private function buildDownloadUrl(mixed $media): ?string
    {
        try {
            $disk = $media->storage_disk ?? 'media';
            $path = $media->storage_path ?? '';

            if ($path === '') {
                return null;
            }

            return Storage::disk($disk)->temporaryUrl($path, now()->addHours(24));
        } catch (\Throwable) {
            return null;
        }
    }
}

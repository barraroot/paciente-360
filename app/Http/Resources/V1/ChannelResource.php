<?php

namespace App\Http\Resources\V1;

use App\Domain\Messaging\Channel\Enums\ChannelProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T075 — Resource de `Channel` para API responses.
 *
 * Não inclui `credentials_encrypted` — LGPD/Princípio I.
 * `provider_metadata` inclui apenas dados não-secretos.
 *
 * @see specs/003-omnichannel-inbox/contracts/openapi.yaml § ChannelResource
 */
final class ChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->provider_metadata) ? $this->provider_metadata : [];

        // Feature 014 — nunca expor segredos de sessão/credenciais (Princípio I).
        $secretKeys = ['instance_token', 'auth_token', 'page_access_token'];
        $safeMetadata = array_diff_key($metadata, array_flip($secretKeys));

        $providerValue = $this->provider instanceof ChannelProvider
            ? $this->provider->value
            : (string) ($this->provider ?? 'twilio');

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'type' => $this->type,
            'provider' => $providerValue,
            'name' => $this->name,
            'status' => $this->status,
            'connected_number' => $metadata['connected_number'] ?? ($metadata['whatsapp_sender'] ?? null),
            'provider_metadata' => $safeMetadata,
            'quality_rating' => $this->quality_rating,
            'quality_rating_updated_at' => $this->quality_rating_updated_at?->toIso8601String(),
            'auto_send_disabled' => $this->auto_send_disabled,
            'last_health_check_at' => $this->last_health_check_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

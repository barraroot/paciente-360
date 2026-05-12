<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T075 — Resource de `ChannelTemplate` para API responses.
 *
 * @see specs/003-omnichannel-inbox/contracts/openapi.yaml § ChannelTemplateResource
 */
final class ChannelTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'provider_template_id' => $this->provider_template_id,
            'meta_template_name' => $this->meta_template_name,
            'meta_template_status' => $this->meta_template_status,
            'language' => $this->language,
            'category' => $this->category,
            'body_preview' => $this->body_preview,
            'variables_schema' => $this->variables_schema ?? [],
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

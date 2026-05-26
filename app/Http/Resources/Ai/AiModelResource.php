<?php

namespace App\Http\Resources\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de leitura do catálogo de modelos para o app do tenant.
 *
 * @mixin AiModel
 */
final class AiModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'internal_identifier' => $this->internal_identifier,
            'description' => $this->description,
            'supports_embedding' => $this->supports_embedding,
            'config_schema' => $this->config_schema ?? [],
            'is_active' => $this->is_active,
        ];
    }
}

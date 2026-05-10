<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de uso de IA (T222).
 *
 * Shape conforme OpenAPI § /billing/ai-usage.
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml
 */
final class AiUsageResource extends JsonResource
{
    /**
     * @param array<string, mixed> $data shape pré-calculado por `AiUsageService::buildResource()`
     */
    public function __construct(private readonly array $data)
    {
        // Não chama parent::__construct() pois o resource recebe array, não Model.
        parent::__construct($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->data;
    }
}

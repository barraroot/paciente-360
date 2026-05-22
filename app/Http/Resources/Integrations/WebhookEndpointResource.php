<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Domain\Integrations\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T199 (Fase 8 — Lote D US-11.1)** — Resource de endpoint.
 *
 * Secret SEMPRE mascarado (`whsec_***`). Plaintext só retornado uma vez
 * no momento do create — via `meta.secret_plaintext` (controller).
 *
 * @mixin WebhookEndpoint
 */
class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'secret_masked' => $this->maskedSecret(),
            'events_subscribed' => $this->events_subscribed?->toArray() ?? [],
            'is_active' => $this->is_active,
            'failure_count' => $this->failure_count,
            'last_success_at' => $this->last_success_at?->toIso8601String(),
            'last_failure_at' => $this->last_failure_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function maskedSecret(): string
    {
        $secret = (string) $this->secret;
        if ($secret === '') {
            return '';
        }

        return 'whsec_'.str_repeat('*', max(0, mb_strlen($secret) - 6));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use App\Services\Panel\DataObjects\AttentionItemDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T036 (Fase 10 — Spec 010 / US-3)** — Resource de item de alerta.
 *
 * @see specs/010-dashboard-home/data-model.md § 1.4
 *
 * @mixin AttentionItemDto
 */
final class AttentionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => __($this->titleKey),
            'description' => $this->description,
            'link' => $this->link,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}

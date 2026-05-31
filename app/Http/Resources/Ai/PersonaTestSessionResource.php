<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Domain\Ai\Persona\Models\PersonaTestSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PersonaTestSession
 */
final class PersonaTestSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'persona_id' => $this->persona_id,
            'status' => $this->status,
            'persona_snapshot' => $this->when($this->relationLoaded('messages') || $request->boolean('include_snapshot'), $this->persona_snapshot),
            'echo_channel' => "private-persona-test.{$this->id}",
            'created_at' => optional($this->created_at)->toIso8601String(),
            'closed_at' => optional($this->closed_at)->toIso8601String(),
            'archived_at' => optional($this->archived_at)->toIso8601String(),
        ];
    }
}

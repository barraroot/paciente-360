<?php

declare(strict_types=1);

namespace App\Http\Resources\Professionals;

use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T015 (Spec 012)** — Resource público de Professional.
 *
 * Princípio I (LGPD): NÃO expõe email do user vinculado nem deleted_at.
 * Email já está disponível em outra surface (lista de Users) — repetir aqui
 * amplia surface de PII sem ganho material.
 *
 * @see specs/012-professionals-management/research.md R9
 *
 * @mixin Professional
 */
final class ProfessionalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'council_type' => $this->council_type,
            'council_type_other' => $this->council_type_other,
            'council_number' => $this->council_number,
            'council_state' => $this->council_state,
            'especialidade' => $this->especialidade,
            'is_active' => (bool) $this->is_active,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

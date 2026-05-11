<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T116 — Resource para `PacienteConvenio` (pivot com papel e carteirinha).
 *
 * @see specs/002-crm-pacientes/contracts/openapi.yaml § PacienteConvenioResource
 */
final class PacienteConvenioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'convenio' => $this->whenLoaded('convenio', fn () => ConvenioResource::make($this->convenio)),
            'numero_carteirinha' => $this->numero_carteirinha,
            'papel' => $this->papel,
        ];
    }
}

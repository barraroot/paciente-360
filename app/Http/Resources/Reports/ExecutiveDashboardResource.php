<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T256 (Fase 8 — Lote E US-10.1)** — Serialização do Dashboard.
 *
 * Espera-se que `$resource` seja o array retornado por
 * `ExecutiveDashboardService::getKpis()`.
 *
 * @property-read array<string, mixed> $resource
 */
class ExecutiveDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

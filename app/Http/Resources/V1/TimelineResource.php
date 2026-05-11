<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T153 — Resource wrapper para a resposta paginada da timeline.
 *
 * Encapsula `data` (array de `EventoTimelineResource`) e `meta` com
 * `next_cursor` e `has_more`.
 *
 * Recebe diretamente o array retornado por `TimelineService::forPaciente`.
 * Os items em `data` são Eloquent models (não arrays) pois o service retorna
 * `$items->values()->all()` (uma Collection de Models).
 *
 * @see App\Services\Pacientes\TimelineService
 */
final class TimelineResource extends JsonResource
{
    /**
     * @param array{data: array<int, mixed>, meta: array{next_cursor: string|null, has_more: bool}} $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $raw = $this->resource;

        return [
            'data' => EventoTimelineResource::collection(collect($raw['data']))->toArray($request),
            'meta' => [
                'next_cursor' => $raw['meta']['next_cursor'],
                'has_more' => $raw['meta']['has_more'],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T010 (Fase 10 — Spec 010)** — Envelope completo da response do
 * Dashboard Home.
 *
 * Estrutura conforme contracts/api-panel-home.md § 1.
 *
 * Cada seção carrega `{ data, error }` para suportar degradação graceful
 * (R13): se um collector falhar, sua seção fica `null + error=true` mas
 * outras permanecem funcionais.
 */
final class PanelHomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return [
            'scope_requested' => $payload['scope_requested'],
            'scope_applied' => $payload['scope_applied'],
            'can_toggle_scope' => $payload['can_toggle_scope'],
            'generated_at' => $payload['generated_at'],
            'cache_hit' => $payload['cache_hit'],
            'sections' => [
                'kpis' => $this->mapSection($payload['sections']['kpis'] ?? null),
                'upcoming_appointments' => $this->mapUpcomingSection($payload['sections']['upcoming_appointments'] ?? null),
                'attention_items' => $this->mapAttentionSection($payload['sections']['attention_items'] ?? null),
                'recent_activity' => $this->mapSection($payload['sections']['recent_activity'] ?? null),
            ],
        ];
    }

    /**
     * @param array{data: mixed, error: bool}|null $section
     * @return array{data: mixed, error: bool}
     */
    private function mapSection(?array $section): array
    {
        if ($section === null) {
            return ['data' => null, 'error' => true];
        }

        return [
            'data' => $section['error'] ? null : $section['data'],
            'error' => (bool) $section['error'],
        ];
    }

    /**
     * @param array{data: mixed, error: bool}|null $section
     * @return array{data: mixed, error: bool}
     */
    private function mapUpcomingSection(?array $section): array
    {
        if ($section === null || $section['error']) {
            return ['data' => null, 'error' => true];
        }

        return [
            'data' => UpcomingAppointmentResource::collection($section['data'])->toArray(request()),
            'error' => false,
        ];
    }

    /**
     * @param array{data: mixed, error: bool}|null $section
     * @return array{data: mixed, error: bool}
     */
    private function mapAttentionSection(?array $section): array
    {
        if ($section === null || $section['error']) {
            return ['data' => null, 'error' => true];
        }

        return [
            'data' => AttentionItemResource::collection($section['data'])->toArray(request()),
            'error' => false,
        ];
    }
}

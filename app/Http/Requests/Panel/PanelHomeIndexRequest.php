<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * **T008 (Fase 10 — Spec 010)** — Request do endpoint Dashboard Home.
 *
 * Valida apenas o query param `scope`. Permission de tenant/auth é tratada
 * pelo middleware stack (`auth:sanctum`, `tenant.slug`, `tenant.not-suspended`).
 */
final class PanelHomeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', 'in:user,clinic'],
        ];
    }

    public function requestedScope(): string
    {
        return $this->string('scope', 'user')->value();
    }
}

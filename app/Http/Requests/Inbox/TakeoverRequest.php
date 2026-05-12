<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T177 — FormRequest para takeover e release-to-ai de IA em conversas.
 *
 * Autorização: ability `inbox.takeover_ai` (Admin, Médico, Atendente, Recepcionista).
 *
 * Campos opcionais:
 *  - duration_hours: int, 1-24 (mutuamente exclusivo com until)
 *  - until: ISO-8601 timestamp futuro (mutuamente exclusivo com duration_hours)
 *  - reason: string, max 255
 *
 * Se nenhum campo informado, usa o padrão do tenant/config.
 */
final class TakeoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inbox.takeover_ai') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:24', 'prohibits:until'],
            'until' => ['nullable', 'date_format:Y-m-d\TH:i:sP', 'after:now', 'prohibits:duration_hours'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

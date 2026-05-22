<?php

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use App\Domain\Privacy\Models\ForgettingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * **T050 (Fase 8 — Lote A US-13.2)** — Confirmação de execução do esquecimento.
 *
 * Admin Clínica precisa de `forgetting.execute` ability.
 */
class ExecuteForgettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ForgettingRequest|null $request */
        $request = $this->route('forgetting');

        return $request !== null && Gate::allows('execute', $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

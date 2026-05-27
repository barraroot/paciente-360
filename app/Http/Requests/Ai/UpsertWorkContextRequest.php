<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai;

use App\Domain\Ai\Services\MarkdownSanitizerService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Feature 017 (US2) — valida o upsert do Contexto de Trabalho.
 *
 * Allow-list ESTRITA de chaves: qualquer campo fora do conjunto comercial/
 * operacional permitido é rejeitado (Princípio III — sem conteúdo clínico).
 */
class UpsertWorkContextRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_KEYS = [
        'services', 'pricing', 'locations', 'deposit_policy',
        'tone', 'qualification_questions', 'free_form', 'is_active',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('ai.work-context.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $freeForm = $this->input('free_form');
        if (is_string($freeForm) && $freeForm !== '') {
            $this->merge(['free_form' => app(MarkdownSanitizerService::class)->sanitize($freeForm)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxQuestions = (int) config('ai.matricial.work_context.max_questions', 8);
        $maxFreeForm = (int) config('ai.matricial.work_context.free_form_max_chars', 4000);

        return [
            'services' => ['sometimes', 'array'],
            'services.*.nome' => ['required', 'string', 'max:150'],
            'services.*.descricao' => ['nullable', 'string', 'max:500'],

            'pricing' => ['sometimes', 'array'],
            'pricing.*.item' => ['required', 'string', 'max:150'],
            'pricing.*.valor_a_vista' => ['nullable', 'string', 'max:60'],
            'pricing.*.valor_cartao' => ['nullable', 'string', 'max:60'],
            'pricing.*.observacao' => ['nullable', 'string', 'max:255'],

            'locations' => ['sometimes', 'array'],
            'locations.*.cidade' => ['required', 'string', 'max:120'],
            'locations.*.endereco' => ['nullable', 'string', 'max:255'],
            'locations.*.observacao' => ['nullable', 'string', 'max:255'],

            'deposit_policy' => ['sometimes', 'array'],
            'deposit_policy.exige_sinal' => ['sometimes', 'boolean'],
            'deposit_policy.percentual' => ['nullable', 'integer', 'min:0', 'max:100'],
            'deposit_policy.meio' => ['nullable', 'string', 'max:60'],
            'deposit_policy.texto' => ['nullable', 'string', 'max:500'],

            'tone' => ['nullable', 'string', 'max:120'],

            'qualification_questions' => ['sometimes', 'array', "max:{$maxQuestions}"],
            'qualification_questions.*' => ['required', 'string', 'max:255'],

            'free_form' => ['nullable', 'string', "max:{$maxFreeForm}"],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpected = array_diff(array_keys($this->all()), self::ALLOWED_KEYS);

            if ($unexpected !== []) {
                foreach ($unexpected as $key) {
                    $validator->errors()->add($key, __('Campo não permitido no contexto de trabalho.'));
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Services\MarkdownSanitizerService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ai.persona.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $sanitizer = app(MarkdownSanitizerService::class);

        foreach (['markdown_content', 'handoff_rules', 'initial_message', 'fallback_message'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && $value !== '') {
                $this->merge([$field => $sanitizer->sanitize($value)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ai_model_id' => ['sometimes', 'integer', Rule::exists('ai_models', 'id')],
            // Feature 018 (T158, US5, FR-037a) — voz TTS plugável.
            'voice_id' => ['nullable', 'integer', Rule::exists('voice_catalog', 'id')->where('is_active', true)],
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'markdown_content' => ['sometimes', 'string', 'max:50000'],
            'tone' => ['nullable', 'string', 'max:120'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'limitations' => ['nullable', 'string', 'max:2000'],
            'initial_message' => ['nullable', 'string', 'max:2000'],
            'fallback_message' => ['nullable', 'string', 'max:2000'],
            'handoff_rules' => ['nullable', 'string', 'max:5000'],
            'model_settings' => ['sometimes', 'array'],
            'model_settings.temperature' => ['sometimes', 'numeric'],
            'model_settings.max_tokens' => ['sometimes', 'integer'],

            // FR-010 — is_active muda apenas via endpoints activate/deactivate.
            'is_active' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $modelId = $this->input('ai_model_id');
            if (! $modelId) {
                return;
            }

            /** @var AiModel|null $model */
            $model = AiModel::find($modelId);
            if (! $model) {
                return;
            }

            // Permite manter o mesmo modelo (mesmo que tenha ficado inativo);
            // só bloqueia TROCA para um modelo inativo (FR-003).
            $persona = $this->route('persona');
            $currentModelId = is_object($persona) ? $persona->ai_model_id : null;

            if (! $model->is_active && (int) $modelId !== (int) $currentModelId) {
                $validator->errors()->add('ai_model_id', __('ai.persona.model_inactive'));

                return;
            }

            PersonaSettingsValidator::validate($validator, $model, (array) $this->input('model_settings', []));
        });
    }
}

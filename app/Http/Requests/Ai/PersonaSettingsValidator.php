<?php

namespace App\Http\Requests\Ai;

use App\Domain\Ai\Model\Models\AiModel;
use Illuminate\Contracts\Validation\Validator;

/**
 * Valida `model_settings` da persona contra o `config_schema` do modelo
 * (FR-008). Cada parâmetro informado deve existir no schema e respeitar
 * os limites min/max declarados.
 */
final class PersonaSettingsValidator
{
    /**
     * @param array<string, mixed> $settings
     */
    public static function validate(Validator $validator, AiModel $model, array $settings): void
    {
        $schema = $model->config_schema ?? [];

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $schema)) {
                $validator->errors()->add("model_settings.{$key}", __('ai.persona.invalid_settings'));

                continue;
            }

            $bounds = $schema[$key];
            if (! is_array($bounds)) {
                continue;
            }

            if (isset($bounds['min']) && is_numeric($value) && $value < $bounds['min']) {
                $validator->errors()->add("model_settings.{$key}", __('ai.persona.invalid_settings'));
            }

            if (isset($bounds['max']) && is_numeric($value) && $value > $bounds['max']) {
                $validator->errors()->add("model_settings.{$key}", __('ai.persona.invalid_settings'));
            }
        }
    }
}

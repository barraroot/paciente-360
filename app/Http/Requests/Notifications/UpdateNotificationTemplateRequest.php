<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Domain\Messaging\Notification\Services\NotificationTemplateResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Feature 013 — Validação de atualização de NotificationTemplate (US5).
 *
 * `notification_type`/`channel_type` são imutáveis (definem a identidade do
 * template); só se ajusta o provider_template_id, idioma, variáveis e ativação.
 */
class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('channel.connect') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider_template_id' => ['sometimes', 'string', 'max:120'],
            'language' => ['sometimes', 'string', 'max:10'],
            'variables_map' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'notification_type' => ['prohibited'],
            'channel_type' => ['prohibited'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $map = $this->input('variables_map');

            if (is_array($map) && ! NotificationTemplateResolver::variablesAreAllowed($map)) {
                $validator->errors()->add(
                    'variables_map',
                    'variables_map contém chave fora da allow-list não-clínica permitida.',
                );
            }
        });
    }
}

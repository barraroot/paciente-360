<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\NotificationTemplateResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Feature 013 — Validação de criação de NotificationTemplate (US5).
 *
 * Autorização: ability `channel.connect` (admin da clínica). Valida tipo/canal
 * e enforça a allow-list não-clínica de `variables_map` (gate LGPD — R9).
 */
class StoreNotificationTemplateRequest extends FormRequest
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
            'notification_type' => ['required', Rule::enum(NotificationType::class)],
            'channel_type' => ['required', 'in:whatsapp'],
            'provider_template_id' => ['required', 'string', 'max:120'],
            'language' => ['required', 'string', 'max:10'],
            'variables_map' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $map = $this->input('variables_map', []);

            if (is_array($map) && ! NotificationTemplateResolver::variablesAreAllowed($map)) {
                $validator->errors()->add(
                    'variables_map',
                    'variables_map contém chave fora da allow-list não-clínica permitida.',
                );
            }
        });
    }
}

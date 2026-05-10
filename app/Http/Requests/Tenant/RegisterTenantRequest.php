<?php

namespace App\Http\Requests\Tenant;

use App\Support\Cnpj\CnpjValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request do cadastro público de tenant (T143 — US-1.1).
 *
 * Validação canônica conforme `openapi.yaml § TenantRegisterRequest`:
 *  - `clinic_name` 3..150 chars.
 *  - `cnpj` 14 dígitos canonicalizados (máscara é removida em
 *    `prepareForValidation`); rejeita DV inválido e duplicidade no DB.
 *  - `slug` opcional; se enviado, valida RFC 1035 (DNS label) e rejeita
 *    slugs reservados (`api`, `admin`, etc.) e hosts públicos
 *    (`crm`, `www`).
 *  - `password` mínima 8 chars com 1 maiúscula + 1 dígito (FR-022).
 *  - `terms_accepted` MUST be true (FR-008 — consentimento explícito).
 *
 * Mensagens em pt-BR via `lang/pt_BR/tenant.php` (FR-042 — i18n).
 *
 * @property string $clinic_name
 * @property string $cnpj
 * @property string|null $slug
 * @property string $responsible_name
 * @property string $responsible_email
 * @property string $responsible_phone
 * @property string $password
 * @property bool $terms_accepted
 * @property string $terms_version
 */
final class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Canonicaliza o CNPJ (apenas dígitos) ANTES da validação para que as
     * regras `regex`, `digits` e `unique` operem sobre o valor canônico.
     */
    protected function prepareForValidation(): void
    {
        $cnpj = $this->input('cnpj');

        if (is_string($cnpj)) {
            $this->merge([
                'cnpj' => preg_replace('/\D+/', '', $cnpj) ?? '',
            ]);
        }

        $slug = $this->input('slug');

        // Slug "" enviado pelo cliente vira null para que o caminho de
        // geração automática rode sem erro de regex.
        if ($slug === '' || $slug === false) {
            $this->merge(['slug' => null]);
        } elseif (is_string($slug)) {
            $this->merge(['slug' => mb_strtolower($slug)]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $reservedSlugs = array_unique(array_merge(
            (array) config('tenancy.reserved_slugs'),
            (array) config('tenancy.public_hosts'),
        ));

        return [
            'clinic_name' => ['required', 'string', 'min:3', 'max:150'],

            'cnpj' => [
                'required',
                'string',
                'size:14',
                'regex:/^\d{14}$/',
                'unique:tenants,cnpj',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! CnpjValidator::isValid($value)) {
                        $fail(__('tenant.register.cnpj_taken'));
                    }
                },
            ],

            'slug' => [
                'nullable',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z]([a-z0-9-]{1,61}[a-z0-9])?$/',
                function (string $attribute, mixed $value, Closure $fail) use ($reservedSlugs): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if (in_array($value, $reservedSlugs, true)) {
                        $fail(__('tenant.register.slug_reserved'));
                    }
                },
                'unique:tenants,slug',
            ],

            'responsible_name' => ['required', 'string', 'min:3', 'max:150'],
            'responsible_email' => ['required', 'string', 'email:rfc', 'max:254'],
            'responsible_phone' => ['required', 'string', 'min:8', 'max:20'],

            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/\d/',
            ],

            'terms_accepted' => ['required', 'accepted'],
            'terms_version' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnpj.unique' => __('tenant.register.cnpj_taken'),
            'cnpj.regex' => __('tenant.register.cnpj_taken'),
            'cnpj.size' => __('tenant.register.cnpj_taken'),

            'slug.regex' => __('tenant.register.slug_invalid'),
            'slug.min' => __('tenant.register.slug_invalid'),
            'slug.max' => __('tenant.register.slug_invalid'),
            'slug.unique' => __('tenant.register.slug_taken'),

            'terms_accepted.accepted' => __('tenant.register.terms_required'),
            'terms_accepted.required' => __('tenant.register.terms_required'),
        ];
    }
}

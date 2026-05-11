<?php

namespace App\Http\Requests\Pacientes;

use App\Support\Tags\TagNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T243 — Form Request para criação de tag livre (US-3.5).
 *
 * Bloqueia o prefixo `sys:` para usuários sem role `super-admin`.
 * A validação de duplicata acontece no `TagService::findOrCreate`.
 */
class CreateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('paciente.create');
    }

    /**
     * Rejeita prefixo `sys:` para usuários que não são super-admin
     * antes da validação do `rules()`.
     */
    protected function prepareForValidation(): void
    {
        $nome = $this->input('nome', '');
        $normalizado = TagNormalizer::normalize($nome);

        if (str_starts_with($normalizado, 'sys:') && ! $this->user()->hasRole('super-admin')) {
            // Mapeia para erro de validação via `withValidator` alternativa
            $this->merge(['nome' => $nome]); // mantém como está; validação abaixo detecta
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => [
                'required',
                'string',
                'min:2',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalizado = TagNormalizer::normalize($value);
                    if (str_starts_with($normalizado, 'sys:') && ! $this->user()->hasRole('super-admin')) {
                        $fail(__('paciente.tag.sys_reservado'));
                    }
                },
            ],
            'cor' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome da tag é obrigatório.',
            'nome.min' => 'O nome da tag deve ter no mínimo 2 caracteres.',
            'nome.max' => 'O nome da tag deve ter no máximo 50 caracteres.',
            'cor.regex' => 'A cor deve estar no formato hexadecimal (#RRGGBB).',
        ];
    }
}

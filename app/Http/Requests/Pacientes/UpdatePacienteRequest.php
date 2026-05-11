<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Paciente;
use App\Support\Cpf\CpfValidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T114 — Form Request para atualização parcial de paciente (PATCH).
 *
 * Status: PATCH aceita todos os 4 status; para transições bloqueadas/
 * desbloqueadas use o endpoint `/status` (PatchStatusController).
 *
 * @see specs/002-crm-pacientes/spec.md US-3.1
 */
class UpdatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paciente = $this->route('paciente') ?? Paciente::findOrFail($this->route('id'));

        return $this->user()->can('update', $paciente);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cpf')) {
            $this->merge(['cpf' => CpfValidator::canonicalize($this->string('cpf')->toString())]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'min:2', 'max:150'],
            'cpf' => ['sometimes', 'nullable', 'string', 'size:11'],
            'documento_estrangeiro' => ['sometimes', 'nullable', 'string', 'max:30'],
            'data_nascimento' => ['sometimes', 'nullable', 'date', 'before:today'],
            'telefone_primario' => ['sometimes', 'nullable', 'string', 'max:20'],
            'telefones_secundarios' => ['sometimes', 'nullable', 'array'],
            'telefones_secundarios.*' => ['string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'endereco' => ['sometimes', 'nullable', 'array'],
            'endereco.logradouro' => ['nullable', 'string', 'max:200'],
            'endereco.numero' => ['nullable', 'string', 'max:20'],
            'endereco.complemento' => ['nullable', 'string', 'max:100'],
            'endereco.bairro' => ['nullable', 'string', 'max:100'],
            'endereco.cidade' => ['nullable', 'string', 'max:100'],
            'endereco.estado' => ['nullable', 'string', 'size:2'],
            'endereco.cep' => ['nullable', 'string', 'max:10'],
            'origem' => ['sometimes', 'nullable', 'string', 'in:site,indicacao,whatsapp,instagram,telefone,presencial,outro'],
            'origem_detalhe' => ['sometimes', 'nullable', 'string', 'max:500'],
            'profissional_responsavel_id' => ['sometimes', 'nullable', 'integer', 'exists:professionals,id'],
            'convenios' => ['sometimes', 'nullable', 'array', 'max:2'],
            'convenios.*.convenio_id' => ['required_with:convenios', 'integer', 'exists:convenios,id'],
            'convenios.*.papel' => ['nullable', 'string', 'in:principal,secundario'],
            'convenios.*.numero_carteirinha' => ['nullable', 'string', 'max:30'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'cpf.size' => __('paciente.cpf_invalido'),
            'data_nascimento.before' => 'A data de nascimento deve ser anterior a hoje.',
            'email.email' => 'O e-mail informado não é válido.',
            'origem.in' => 'Origem inválida.',
        ];
    }
}

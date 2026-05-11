<?php

namespace App\Http\Requests\Pacientes;

use App\Models\Paciente;
use App\Support\Cpf\CpfValidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T114 — Form Request para criação de paciente (US-3.1).
 *
 * `prepareForValidation` canonicaliza o CPF antes das regras serem avaliadas.
 * A validação de DV é feita no `PacienteService` (não via rule aqui, para
 * que o erro venha com mensagem pt-BR consistente).
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.1.1, AC-3.1.2, AC-3.1.5
 */
class CreatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Paciente::class);
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
            'nome' => ['required', 'string', 'min:2', 'max:150'],
            'cpf' => ['nullable', 'string', 'size:11'],
            'documento_estrangeiro' => ['nullable', 'string', 'max:30'],
            'data_nascimento' => ['nullable', 'date', 'before:today'],
            'telefone_primario' => ['nullable', 'string', 'max:20'],
            'telefones_secundarios' => ['nullable', 'array'],
            'telefones_secundarios.*' => ['string', 'max:20'],
            'email' => ['nullable', 'email', 'max:254'],
            'endereco' => ['nullable', 'array'],
            'endereco.logradouro' => ['nullable', 'string', 'max:200'],
            'endereco.numero' => ['nullable', 'string', 'max:20'],
            'endereco.complemento' => ['nullable', 'string', 'max:100'],
            'endereco.bairro' => ['nullable', 'string', 'max:100'],
            'endereco.cidade' => ['nullable', 'string', 'max:100'],
            'endereco.estado' => ['nullable', 'string', 'size:2'],
            'endereco.cep' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'in:lead,ativo'],
            'origem' => ['nullable', 'string', 'in:site,indicacao,whatsapp,instagram,telefone,presencial,outro'],
            'origem_detalhe' => ['nullable', 'string', 'max:500'],
            'profissional_responsavel_id' => ['nullable', 'integer', 'exists:professionals,id'],
            'convenios' => ['nullable', 'array', 'max:2'],
            'convenios.*.convenio_id' => ['required_with:convenios', 'integer', 'exists:convenios,id'],
            'convenios.*.papel' => ['nullable', 'string', 'in:principal,secundario'],
            'convenios.*.numero_carteirinha' => ['nullable', 'string', 'max:30'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'ignorar_duplicata' => ['nullable', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do paciente é obrigatório.',
            'nome.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'nome.max' => 'O nome não pode ter mais de 150 caracteres.',
            'cpf.size' => __('paciente.cpf_invalido'),
            'data_nascimento.before' => 'A data de nascimento deve ser anterior a hoje.',
            'email.email' => 'O e-mail informado não é válido.',
            'status.in' => 'O status inicial deve ser "lead" ou "ativo".',
            'origem.in' => 'Origem inválida. Valores aceitos: site, indicacao, whatsapp, instagram, telefone, presencial, outro.',
            'convenios.max' => 'Um paciente pode ter no máximo 2 convênios.',
        ];
    }
}

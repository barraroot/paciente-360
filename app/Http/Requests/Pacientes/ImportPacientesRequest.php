<?php

namespace App\Http\Requests\Pacientes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T185 — Form Request para upload de arquivo de importação de pacientes.
 *
 * Validações de tamanho máximo e extensão são feitas aqui (camada HTTP).
 * Validações mais complexas (contagem de linhas, cabeçalho) ficam no
 * `ImportacaoService` e retornam InvalidArgumentException → 422.
 */
final class ImportPacientesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('paciente.import') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = config('paciente.import.max_size_mb', 5) * 1024;

        return [
            'arquivo' => [
                'required',
                'file',
                'mimes:csv,xlsx,xls,txt',
                "max:{$maxKb}",
            ],
            'status_inicial' => ['required', 'in:lead,ativo'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arquivo.required' => 'O arquivo de importação é obrigatório.',
            'arquivo.file' => 'O campo arquivo deve ser um arquivo válido.',
            'arquivo.mimes' => 'O arquivo deve estar no formato CSV ou XLSX.',
            'arquivo.max' => 'O arquivo não pode ser maior que '.config('paciente.import.max_size_mb', 5).'MB.',
            'status_inicial.required' => 'O status inicial dos pacientes importados é obrigatório.',
            'status_inicial.in' => 'O status inicial deve ser "lead" ou "ativo".',
        ];
    }
}

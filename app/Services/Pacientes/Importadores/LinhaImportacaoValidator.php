<?php

namespace App\Services\Pacientes\Importadores;

use App\Support\Cpf\CpfValidator;
use App\Support\Telefone\TelefoneNormalizer;

/**
 * T183 — Validador de linha individual de importação de pacientes.
 *
 * Cada linha é validada independentemente; erros são coletados para o
 * relatório. Dados válidos são normalizados antes de retornar.
 */
final class LinhaImportacaoValidator
{
    private const STATUS_VALIDOS = ['lead', 'ativo', 'inativo', 'bloqueado'];

    private const ORIGEM_VALIDA = ['site', 'indicacao', 'whatsapp', 'instagram', 'telefone', 'presencial', 'outro'];

    /**
     * Valida e normaliza uma linha de dados de importação.
     *
     * @param array<string, string> $linhaData
     * @return array{ok: bool, motivo: string|null, data_normalizada: array<string, mixed>}
     */
    public function validate(array $linhaData): array
    {
        $erros = [];
        $data = [];

        // Nome: obrigatório
        $nome = trim($linhaData['nome'] ?? '');
        if ($nome === '') {
            $erros[] = 'nome obrigatório';
        } else {
            $data['nome'] = $nome;
        }

        // CPF: opcional, valida DV se preenchido
        $cpfRaw = trim($linhaData['cpf'] ?? '');
        if ($cpfRaw !== '') {
            $cpfCanon = CpfValidator::canonicalize($cpfRaw);
            if (! CpfValidator::isValid($cpfCanon)) {
                $erros[] = 'cpf inválido';
            } else {
                $data['cpf'] = $cpfCanon;
            }
        } else {
            $data['cpf'] = null;
        }

        // Telefone primário: opcional, normaliza se preenchido
        $telefoneRaw = trim($linhaData['telefone_primario'] ?? '');
        if ($telefoneRaw !== '') {
            try {
                $data['telefone_primario'] = TelefoneNormalizer::normalize($telefoneRaw);
            } catch (\InvalidArgumentException) {
                // Telefone inválido: inclui no erro mas não impede importação
                $erros[] = 'telefone_primario inválido';
                $data['telefone_primario'] = null;
            }
        } else {
            $data['telefone_primario'] = null;
        }

        // E-mail: opcional, valida formato
        $email = trim($linhaData['email'] ?? '');
        if ($email !== '') {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erros[] = 'email inválido';
            } else {
                $data['email'] = $email;
            }
        } else {
            $data['email'] = null;
        }

        // Data de nascimento: opcional, normaliza para ISO
        $dataNasc = trim($linhaData['data_nascimento'] ?? '');
        if ($dataNasc !== '') {
            $iso = $this->normalizarData($dataNasc);
            if ($iso !== null) {
                $data['data_nascimento'] = $iso;
            } else {
                $erros[] = 'data_nascimento inválida (use DD/MM/AAAA ou YYYY-MM-DD)';
            }
        }

        // Status: opcional na linha, sobrepõe statusInicial se fornecido + válido
        $status = trim($linhaData['status'] ?? '');
        if ($status !== '' && in_array($status, self::STATUS_VALIDOS, true)) {
            $data['status_linha'] = $status; // flag para sobrepor statusInicial
        }

        // Origem: opcional, valida enum
        $origem = trim($linhaData['origem'] ?? '');
        if ($origem !== '') {
            if (in_array($origem, self::ORIGEM_VALIDA, true)) {
                $data['origem'] = $origem;
            }
        }

        // Origem detalhe
        $origemDetalhe = trim($linhaData['origem_detalhe'] ?? '');
        if ($origemDetalhe !== '') {
            $data['origem_detalhe'] = $origemDetalhe;
        }

        if (! empty($erros)) {
            return [
                'ok' => false,
                'motivo' => implode('; ', $erros),
                'data_normalizada' => [],
            ];
        }

        return [
            'ok' => true,
            'motivo' => null,
            'data_normalizada' => $data,
        ];
    }

    /**
     * Normaliza data para ISO 8601 (YYYY-MM-DD).
     * Aceita DD/MM/AAAA ou YYYY-MM-DD.
     */
    private function normalizarData(string $raw): ?string
    {
        // DD/MM/AAAA
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            $date = \DateTime::createFromFormat('d/m/Y', $raw);
            if ($date && $date->format('d/m/Y') === $raw) {
                return $date->format('Y-m-d');
            }
        }

        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $date = \DateTime::createFromFormat('Y-m-d', $raw);
            if ($date && $date->format('Y-m-d') === $raw) {
                return $raw;
            }
        }

        return null;
    }
}

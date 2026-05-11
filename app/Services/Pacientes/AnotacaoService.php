<?php

namespace App\Services\Pacientes;

use App\Events\Paciente\AnotacaoCriada;
use App\Events\Paciente\AnotacaoRetratada;
use App\Models\Anotacao;
use App\Models\Paciente;
use App\Models\User;

/**
 * T151 — Serviço de domínio para criação e retratação de anotações.
 *
 * Anotações são imutáveis (trigger PG + boot do Model). Qualquer
 * "edição" ocorre via retratação: uma nova anotação é criada linkando
 * `retratacao_de_anotacao_id` à original, que permanece intacta.
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.2.4, AC-3.2.5
 */
final class AnotacaoService
{
    /**
     * Cria uma nova anotação para o paciente e dispara `AnotacaoCriada`.
     *
     * @param array<string, mixed> $data Deve conter `tipo` e `texto`.
     */
    public function create(Paciente $paciente, array $data, User $autor): Anotacao
    {
        /** @var Anotacao $anotacao */
        $anotacao = Anotacao::create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'tipo' => $data['tipo'],
            'texto' => $data['texto'],
            'autor_id' => $autor->id,
            'retratacao_de_anotacao_id' => null,
        ]);

        AnotacaoCriada::dispatch($anotacao);

        return $anotacao;
    }

    /**
     * Cria uma retratação de uma anotação original.
     *
     * A anotação original permanece intacta; uma nova é criada com
     * `retratacao_de_anotacao_id` apontando para a original.
     *
     * @throws \InvalidArgumentException Se a anotação original já for uma retratação.
     */
    public function retratar(Anotacao $original, string $texto, User $autor): Anotacao
    {
        if ($original->retratacao_de_anotacao_id !== null) {
            throw new \InvalidArgumentException('anotacao_ja_eh_retratacao');
        }

        /** @var Anotacao $retratacao */
        $retratacao = Anotacao::create([
            'tenant_id' => $original->tenant_id,
            'paciente_id' => $original->paciente_id,
            'tipo' => $original->tipo,
            'texto' => $texto,
            'autor_id' => $autor->id,
            'retratacao_de_anotacao_id' => $original->id,
        ]);

        AnotacaoRetratada::dispatch($original, $retratacao);

        return $retratacao;
    }
}

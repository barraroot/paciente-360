<?php

namespace App\Services\Funil;

use App\Events\Paciente\LeadMovidoNoFunil;
use App\Models\FunilColuna;
use App\Models\Paciente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T211 — Serviço de movimentação de cards no Funil Kanban (US-3.4).
 *
 * `moveCard` é o único ponto de entrada para alterar a posição de um paciente
 * no funil — manual (drag-and-drop) ou automática (regras Fase 5+). A flag
 * `$automatico` distingue as duas origens no payload do evento.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.4 AC-3.4.3, AC-3.4.4, AC-3.4.5
 */
final class FunilService
{
    /**
     * Move um paciente para outra coluna do funil.
     *
     * @throws ValidationException quando motivo obrigatório não é fornecido
     *                             ou `motivo_outro` não atende o mínimo de 10 chars
     */
    public function moveCard(
        Paciente $paciente,
        FunilColuna $destino,
        ?float $posicao,
        ?string $motivo,
        ?string $motivoOutro,
        bool $automatico = false,
    ): Paciente {
        return DB::transaction(function () use ($paciente, $destino, $posicao, $motivo, $motivoOutro, $automatico): Paciente {
            // Validação de negócio: motivo obrigatório
            if ($destino->motivo_obrigatorio && empty($motivo)) {
                throw ValidationException::withMessages([
                    'motivo' => [__('paciente.funil.motivo_obrigatorio')],
                ]);
            }

            // Validação: motivo_outro obrigatório com conteúdo mínimo
            if ($motivo === 'outro') {
                if (empty($motivoOutro) || mb_strlen($motivoOutro) < 10) {
                    throw ValidationException::withMessages([
                        'motivo_outro' => [__('paciente.funil.motivo_outro_obrigatorio')],
                    ]);
                }
            }

            /** @var FunilColuna|null $colunaAnterior */
            $colunaAnterior = $paciente->funilColuna;

            $paciente->funil_coluna_atual_id = $destino->id;
            $paciente->funil_posicao = $posicao ?? 1.0;
            $paciente->save();

            LeadMovidoNoFunil::dispatch(
                paciente: $paciente,
                colunaAnterior: $colunaAnterior,
                colunaNova: $destino,
                motivo: $motivo,
                motivoOutro: $motivoOutro,
                automatico: $automatico,
            );

            return $paciente->fresh(['funilColuna']);
        });
    }
}

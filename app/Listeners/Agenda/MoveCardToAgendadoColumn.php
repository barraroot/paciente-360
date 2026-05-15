<?php

namespace App\Listeners\Agenda;

use App\Events\Agenda\ConsultaCriada;
use App\Models\FunilColuna;
use Illuminate\Support\Facades\Log;

/**
 * T073 — Move card do paciente no funil (Fase 2) para coluna "Agendado"
 * quando consulta é criada (FR-013 / AC-6.3.1).
 *
 * Idempotente: se paciente já está na coluna, não faz nada.
 */
class MoveCardToAgendadoColumn
{
    public function handle(ConsultaCriada $event): void
    {
        $paciente = $event->appointment->paciente;

        if (! $paciente) {
            return;
        }

        $coluna = FunilColuna::query()
            ->where('tenant_id', $event->appointment->tenant_id)
            ->where(function ($q) {
                $q->whereRaw('LOWER(nome) = ?', ['agendado'])
                    ->orWhere('slug', 'agendado');
            })
            ->first();

        if (! $coluna) {
            Log::debug('agenda.funil.coluna_agendado_not_found', [
                'tenant_id' => $event->appointment->tenant_id,
                'appointment_id' => $event->appointment->id,
            ]);

            return;
        }

        if ($paciente->funil_coluna_atual_id !== $coluna->id) {
            $paciente->funil_coluna_atual_id = $coluna->id;
            $paciente->save();
        }
    }
}

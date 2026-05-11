<?php

namespace App\Http\Controllers\Api\V1\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\PatchStatusRequest;
use App\Http\Resources\V1\PacienteResource;
use App\Models\Paciente;
use App\Services\Pacientes\StatusMachineService;

/**
 * T119 — Controller invocável para patch de status do paciente.
 *
 * Transições válidas delegadas ao `StatusMachineService`.
 * Autorização via `PatchStatusRequest::authorize()` (PacientePolicy::update).
 *
 * @group CRM Pacientes
 *
 * @see App\Services\Pacientes\StatusMachineService
 */
class PatchStatusController extends Controller
{
    public function __construct(
        private readonly StatusMachineService $statusMachine,
    ) {}

    public function __invoke(int $id, PatchStatusRequest $request): PacienteResource
    {
        $paciente = Paciente::findOrFail($id);
        $validated = $request->validated();

        $paciente = $this->statusMachine->transition(
            $paciente,
            $validated['status'],
            $request->user(),
            $validated['motivo'] ?? null,
        );

        return PacienteResource::make($paciente);
    }
}

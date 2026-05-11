<?php

namespace App\Events\Professional;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Professional;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event disparado quando um profissional é desativado (is_active: true → false).
 *
 * Listener `ProfessionalDeactivatedListener` cria `TarefaReatribuicao` com
 * lista de pacientes órfãos e dispara `ReassignOrphansJob`.
 */
class ProfessionalDeactivated implements Auditable
{
    use Dispatchable;
    use InteractsWithSockets;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public Professional $professional,
        /** @var array<int> $orphanPacienteIds */
        public array $orphanPacienteIds = [],
    ) {}

    public function auditAction(): string
    {
        return 'profissional.desativado';
    }

    public function auditPayload(): array
    {
        return [
            'professional_id' => $this->professional->id,
            'professional_name' => $this->professional->name,
            'orphan_paciente_ids' => $this->orphanPacienteIds,
            'orphan_count' => count($this->orphanPacienteIds),
        ];
    }

    public function auditableModel(): Professional
    {
        return $this->professional;
    }
}

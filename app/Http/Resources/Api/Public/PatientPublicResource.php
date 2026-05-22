<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Public;

use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **T229 (Fase 8 — Lote D US-11.2)** — Paciente exposto pela API pública.
 *
 * Aplica mascaramento adicional sob LGPD:
 *   - Paciente sem consentimento `Integracoes` → expõe apenas {id, status}.
 *   - Campos sensíveis (cpf, observacoes) ocultos por padrão.
 *
 * @mixin Paciente
 */
class PatientPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $consentService = app(ConsentService::class);
        $hasConsent = $consentService->hasGranted($this->id, ConsentFinalidade::Integracoes);

        if (! $hasConsent) {
            return [
                'id' => $this->id,
                'consent_status' => 'withheld',
                'note' => 'Paciente não autorizou compartilhamento com integrações externas.',
            ];
        }

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'data_nascimento' => $this->data_nascimento?->toDateString(),
            'genero' => $this->genero,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

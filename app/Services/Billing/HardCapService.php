<?php

namespace App\Services\Billing;

use App\Events\Billing\HardCapConfigured;
use App\Models\AiUsageMeter;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;

/**
 * Serviço de configuração do hard cap de uso de IA (T223).
 *
 * Regras:
 *  - `setHardCap(null)` remove o cap (sem bloqueio).
 *  - `setHardCap(0)` desativa IA imediatamente (cap zerado → sempre atingido).
 *  - Se `$cap <= $meter->messages_count`, o cap é acionado imediatamente.
 *  - Auditoria via `HardCapConfigured` event (audita old/new cap).
 */
final class HardCapService
{
    public function __construct(
        private readonly AiUsageService $aiUsageService,
    ) {}

    /**
     * Define ou remove o hard cap do tenant no ciclo atual.
     *
     * @param int|null $cap NULL = remove; 0 = desativa IA imediato.
     */
    public function setHardCap(Tenant $tenant, ?int $cap): AiUsageMeter
    {
        $meter = $this->aiUsageService->getCurrentMeter($tenant);

        $oldCap = $meter->hard_cap;

        $meter->hard_cap = $cap;

        // Se o cap está sendo removido, limpa o trigger anterior.
        if ($cap === null) {
            $meter->hard_cap_triggered_at = null;
        }

        $meter->save();

        // Se novo cap <= consumo atual (incluindo 0 <= 0), aciona imediatamente.
        if ($cap !== null && $meter->hard_cap_triggered_at === null && $cap <= $meter->messages_count) {
            $this->aiUsageService->triggerHardCap($meter, $tenant);
            $meter->refresh();
        }

        Event::dispatch(new HardCapConfigured($tenant, $oldCap, $cap));

        return $meter;
    }
}

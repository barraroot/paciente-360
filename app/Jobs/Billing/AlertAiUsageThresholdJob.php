<?php

namespace App\Jobs\Billing;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AiUsageThresholdNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Job que envia alerta de threshold de uso de IA (T224).
 *
 * Disparado por `AiUsageService::recordUsage()` quando o consumo cruza
 * 80% ou 100% da cota incluída. Idempotente: verifica AuditLog antes de
 * enviar para garantir que o alerta é enviado apenas uma vez por ciclo
 * e threshold.
 *
 * Não estende `TenantAwareJob` porque o `tenantId` é passado explicitamente
 * (pode ser chamado de contexto sem tenant no container — ex.: CLI).
 */
final class AlertAiUsageThresholdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $threshold,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($this->tenantId);

        app()->instance('tenant', $tenant);

        // Deduplicação: verifica se já foi enviado no ciclo atual.
        $cycleStart = Carbon::now('America/Sao_Paulo')->startOfMonth();

        $alreadySent = AuditLog::query()
            ->where('tenant_id', $this->tenantId)
            ->where('action', 'ai_usage.threshold.alerted')
            ->where('created_at', '>=', $cycleStart)
            ->whereJsonContains('payload->threshold', $this->threshold)
            ->exists();

        if ($alreadySent) {
            return;
        }

        // Notifica Admin Clínica e Financeiro.
        $users = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin-clinica', 'financeiro']))
            ->get();

        $notification = new AiUsageThresholdNotification($this->threshold);

        foreach ($users as $user) {
            $user->notify($notification);
        }

        // Grava audit de deduplicação.
        AuditLog::create([
            'tenant_id' => $this->tenantId,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => 'ai_usage.threshold.alerted',
            'auditable_type' => null,
            'auditable_id' => null,
            'payload' => ['threshold' => $this->threshold],
            'ip' => null,
            'user_agent' => null,
            'request_id' => null,
        ]);
    }
}

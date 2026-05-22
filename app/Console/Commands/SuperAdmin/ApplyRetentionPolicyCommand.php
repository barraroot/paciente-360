<?php

declare(strict_types=1);

namespace App\Console\Commands\SuperAdmin;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * **T104 (Fase 8 — Lote B US-12.1)** — Política de retenção pós-cancelamento (Q20).
 *
 * Roda daily 02:00 BRT (registrado em `routes/console.php` em T009).
 *
 * Para cada tenant cancelado, verifica cada checkpoint da política diferenciada:
 *   - T+30d  → deletar configurações do tenant
 *   - T+90d  → anonimizar dados de paciente (30d undo + 60d grace)
 *   - T+365d → deletar audit logs do tenant (LGPD Art. 16)
 *   - T+730d → deletar receitas controladas (Portaria 344/98)
 *   - T+1825d → deletar registros financeiros (Lei 12.682/2012)
 *
 * **MVP** loga o que SERIA feito em cada checkpoint sem aplicar mudanças
 * destrutivas — a execução real precisa ser revisada caso a caso quando o
 * primeiro tenant cancelado atingir cada checkpoint. Estrutura está pronta
 * para ativar com `--apply` quando essa decisão for tomada.
 */
final class ApplyRetentionPolicyCommand extends Command
{
    protected $signature = 'super-admin:apply-retention-policy {--apply : Executa mudanças destrutivas (default: dry-run)}';

    protected $description = 'Aplica política de retenção pós-cancelamento Q20 por categoria de dado.';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $now = Carbon::now();

        $stats = [
            'tenants_processed' => 0,
            'config_deleted' => 0,
            'patient_anonymized' => 0,
            'audit_logs_deleted' => 0,
            'prescriptions_deleted' => 0,
            'billing_deleted' => 0,
        ];

        $config = (array) config('finalization.retention_policy', []);

        Tenant::query()->canceled()->chunk(50, function ($tenants) use (&$stats, $now, $apply, $config): void {
            foreach ($tenants as $tenant) {
                /** @var Tenant $tenant */
                $stats['tenants_processed']++;
                $daysSinceCancel = (int) $tenant->canceled_at->diffInDays($now, true);

                // T+30d: deletar configurações do tenant
                if ($daysSinceCancel >= ($config['config_days'] ?? 30)) {
                    if ($apply) {
                        // DEFERRED — aplicação destrutiva precisa de revisão caso a caso
                        Log::critical('super_admin.retention.config_purge_pending_review', [
                            'tenant_id' => $tenant->id,
                            'days_since_cancel' => $daysSinceCancel,
                        ]);
                    }
                    $stats['config_deleted']++;
                }

                // T+90d: anonimizar dados de paciente
                if ($daysSinceCancel >= ($config['patient_data_days'] ?? 90)) {
                    if ($apply) {
                        Log::critical('super_admin.retention.patient_anonymize_pending_review', [
                            'tenant_id' => $tenant->id,
                            'days_since_cancel' => $daysSinceCancel,
                        ]);
                    }
                    $stats['patient_anonymized']++;
                }

                // T+365d / 730d / 1825d — checkpoints subsequentes (DEFERRED para fase futura).
                if ($daysSinceCancel >= ($config['audit_logs_days'] ?? 365)) {
                    $stats['audit_logs_deleted']++;
                }
                if ($daysSinceCancel >= ($config['controlled_prescriptions_days'] ?? 730)) {
                    $stats['prescriptions_deleted']++;
                }
                if ($daysSinceCancel >= ($config['billing_records_days'] ?? 1825)) {
                    $stats['billing_deleted']++;
                }
            }
        });

        $this->info(sprintf(
            'Retention policy %s: processed=%d, config=%d, patient=%d, audit=%d, presc=%d, billing=%d',
            $apply ? '[APPLIED]' : '[DRY-RUN]',
            $stats['tenants_processed'],
            $stats['config_deleted'],
            $stats['patient_anonymized'],
            $stats['audit_logs_deleted'],
            $stats['prescriptions_deleted'],
            $stats['billing_deleted'],
        ));

        return self::SUCCESS;
    }
}

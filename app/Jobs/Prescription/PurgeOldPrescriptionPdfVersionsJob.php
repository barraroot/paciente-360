<?php

namespace App\Jobs\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Support\Metrics\PrescriptionMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * T172 — Job de manutenção: purge de versões antigas de PDFs de receitas.
 *
 * Para receitas `type != controlled` com `pdf_version > 5`:
 *  - Registra candidatos a purge (stub: Log::info por enquanto, sem S3 real).
 *  - Receitas controladas: skip (todas as versões preservadas — research §2).
 *
 * DEFERRED: A remoção real via S3 requer integração com `aws/aws-sdk-php`
 * e credenciais de produção. Substituir o Log::info por chamada real ao
 * Storage::delete() quando o ambiente S3 estiver disponível.
 *
 * Queue: prescriptions-maintenance
 * Idempotente: roda em loop sem efeito duplo.
 */
class PurgeOldPrescriptionPdfVersionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Máximo de versões retidas por receita (não controlada).
     */
    private const MAX_VERSIONS = 5;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    public function __construct()
    {
        $this->onQueue('prescriptions-maintenance');
    }

    public function handle(PrescriptionMetrics $metrics): void
    {
        // Busca receitas NÃO controladas com versão acima do limite
        Prescription::withoutGlobalScopes()
            ->where('type', '!=', PrescriptionType::Controlled->value)
            ->where('pdf_version', '>', self::MAX_VERSIONS)
            ->lazyById()
            ->each(function (Prescription $prescription) use ($metrics): void {
                $this->purgeOldVersions($prescription, $metrics);
            });
    }

    /**
     * Registra candidatos a purge para a receita.
     *
     * DEFERRED — S3 real: substituir Log::info por Storage::disk('s3')->delete()
     * quando credenciais de produção estiverem disponíveis.
     */
    private function purgeOldVersions(Prescription $prescription, PrescriptionMetrics $metrics): void
    {
        $currentVersion = $prescription->pdf_version;
        $purgableCount = $currentVersion - self::MAX_VERSIONS;

        for ($v = 0; $v < $purgableCount; $v++) {
            $versionPath = "prescriptions/{$prescription->tenant_id}/{$prescription->id}/v{$v}.pdf";

            // DEFERRED: Storage::disk('s3')->delete($versionPath);
            Log::info('prescription.pdf.purge_candidate', [
                'prescription_id' => $prescription->id,
                'tenant_id' => $prescription->tenant_id,
                'version' => $v,
                'path' => $versionPath,
                'current_version' => $currentVersion,
            ]);
        }

        $metrics->pdfUploaded($prescription->tenant_id, 'purged');
    }
}

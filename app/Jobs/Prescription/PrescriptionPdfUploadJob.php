<?php

namespace App\Jobs\Prescription;

use App\Domain\Prescription\Pdf\PrescriptionPdfStorage;
use App\Domain\Prescription\Pdf\PrescriptionPdfVersioningService;
use App\Domain\Prescription\Prescription\Prescription;
use App\Events\Prescription\PrescricaoAtualizada;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * T071 — Job assíncrono de upload de PDF de receita para S3 (AC-8.1.4).
 *
 * Recebe `prescription_id` + `tmp_path` (path local temporário).
 * Copia para S3 em `prescriptions/{tenant_id}/{prescription_id}/v{next}.pdf`.
 * Atualiza `pdf_path` + `pdf_version` na receita.
 * Emite `PrescricaoAtualizada{changed_fields:['pdf_path']}`.
 *
 * Idempotente: em retry, calcula versão com base no pdf_version atual.
 * Falha não modifica DB.
 */
class PrescriptionPdfUploadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $prescriptionId,
        public readonly string $tmpPath,
    ) {
        $this->onQueue('prescriptions-pdf');
    }

    public function handle(): void
    {
        $prescription = Prescription::withoutGlobalScopes()->find($this->prescriptionId);

        if ($prescription === null) {
            Log::warning('PrescriptionPdfUploadJob: prescription not found', [
                'prescription_id' => $this->prescriptionId,
            ]);

            return;
        }

        $storage = new PrescriptionPdfStorage;
        $versioning = new PrescriptionPdfVersioningService;

        $isReplacement = $prescription->pdf_path !== null;
        $nextVersion = $versioning->nextVersion($prescription);
        $s3Path = $storage->pathFor($prescription, $nextVersion);

        // Read tmp file and upload to S3
        $tmpDisk = Storage::disk('local');

        if (! $tmpDisk->exists($this->tmpPath)) {
            Log::warning('PrescriptionPdfUploadJob: tmp file not found', [
                'tmp_path' => $this->tmpPath,
                'prescription_id' => $this->prescriptionId,
            ]);

            return;
        }

        $stream = $tmpDisk->readStream($this->tmpPath);

        try {
            $storage->put($s3Path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // Update prescription only after successful S3 upload
        $prescription->update([
            'pdf_path' => $s3Path,
            'pdf_version' => $nextVersion,
        ]);

        // Record audit log for replacement
        if ($isReplacement) {
            try {
                AuditLog::create([
                    'tenant_id' => $prescription->tenant_id,
                    'user_id' => null,
                    'actor_type' => 'system',
                    'action' => 'prescription.pdf_replaced',
                    'auditable_type' => Prescription::class,
                    'auditable_id' => $prescription->id,
                    'payload' => [
                        'new_version' => $nextVersion,
                        'new_path' => $s3Path,
                    ],
                ]);
            } catch (\Throwable) {
                // Audit failure must not fail the job
            }
        }

        PrescricaoAtualizada::dispatch(
            prescriptionId: $prescription->id,
            changedFields: ['pdf_path'],
        );

        // Clean up tmp file
        try {
            $tmpDisk->delete($this->tmpPath);
        } catch (\Throwable) {
            // Cleanup failure is non-critical
        }
    }
}

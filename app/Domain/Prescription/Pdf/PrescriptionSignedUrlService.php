<?php

namespace App\Domain\Prescription\Pdf;

use App\Domain\Prescription\Prescription\Prescription;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * T074 — Gera URL assinada com TTL 15min para download de PDF (FR-033).
 *
 * Grava audit log `prescription.pdf_signed_url_issued` com
 * prescription_id + user_id + ip.
 */
class PrescriptionSignedUrlService
{
    public function __construct(
        private readonly PrescriptionPdfStorage $storage,
    ) {}

    /**
     * @return array{url: string, expires_at: string}
     */
    public function sign(Prescription $prescription, User $by): array
    {
        $url = $this->storage->temporaryUrl($prescription->pdf_path, 15);
        $expiresAt = now()->addMinutes(15)->toIso8601String();

        try {
            AuditLog::create([
                'tenant_id' => $prescription->tenant_id,
                'user_id' => $by->id,
                'actor_type' => 'user',
                'action' => 'prescription.pdf_signed_url_issued',
                'auditable_type' => Prescription::class,
                'auditable_id' => $prescription->id,
                'payload' => [
                    'prescription_id' => $prescription->id,
                    'pdf_version' => $prescription->pdf_version,
                    'expires_at' => $expiresAt,
                ],
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable) {
            // Audit failures never block sign URL generation
        }

        return ['url' => $url, 'expires_at' => $expiresAt];
    }
}

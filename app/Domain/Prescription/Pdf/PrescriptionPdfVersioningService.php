<?php

namespace App\Domain\Prescription\Pdf;

use App\Domain\Prescription\Prescription\Prescription;

/**
 * T073 — Versionamento de PDFs de receita (Q7b).
 *
 * Versões antigas ficam preservadas em S3 por path imutável `v{n}.pdf`.
 * Substituição não sobrescreve — apenas grava novo arquivo em path v{n+1}.
 */
class PrescriptionPdfVersioningService
{
    /**
     * Retorna o próximo número de versão para upload.
     * `pdf_version=0` significa nenhum upload feito ainda.
     */
    public function nextVersion(Prescription $prescription): int
    {
        return ($prescription->pdf_version ?? 0) + 1;
    }

    /**
     * Archive é no-op: o path antigo (`v{n}.pdf`) é imutável em S3
     * e nunca é sobrescrito — a nova versão vai para `v{n+1}.pdf`.
     *
     * Mantido para contrato futuro quando houver soft-delete de versões.
     */
    public function archive(Prescription $prescription): void
    {
        // No-op: versões anteriores preservadas por path imutável
    }
}

<?php

namespace App\Support\Metrics;

use App\Domain\Prescription\Renewal\InitiatedByType;

interface PrescriptionMetricsContract
{
    public function alertDispatchedTotal(int $tenantId, string $alertStep, string $status): void;

    /** T113 — Alias legível para alertDispatchedTotal, usado no listener. */
    public function alertDispatched(int $tenantId, string $alertStep, string $status): void;

    /** T113 — Counter de alertas bloqueados por motivo. */
    public function alertBlocked(string $reason, int $tenantId): void;

    /** T113 — Counter de hits de idempotência Redis. */
    public function alertIdempotencyHit(int $tenantId, string $alertType): void;

    /** T113 — Counter/gauge de alertas processados pelo job principal. */
    public function alertsProcessed(int $tenantId, int $count): void;

    /** T148 — Counter de renovações iniciadas por tipo e tenant. */
    public function renewalInitiated(int $tenantId, InitiatedByType $by): void;

    public function renewalConversionRate(int $tenantId, float $rate): void;

    public function controlledAccessDeniedTotal(int $tenantId): void;

    public function pdfUploadedTotal(string $status): void;

    /** T174 — Counter de uploads de PDF por tenant e status. */
    public function pdfUploaded(int $tenantId, string $status): void;

    /** T174 — Counter de signed URLs emitidas por tenant. */
    public function signedUrlEmitted(int $tenantId): void;

    /** T174 — Counter de exportações CSV por tenant (com flag de controlada). */
    public function csvExported(int $tenantId, int $count, bool $hasControlled): void;
}

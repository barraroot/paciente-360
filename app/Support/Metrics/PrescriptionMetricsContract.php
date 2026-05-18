<?php

namespace App\Support\Metrics;

interface PrescriptionMetricsContract
{
    public function alertDispatchedTotal(int $tenantId, string $alertStep, string $status): void;

    public function renewalConversionRate(int $tenantId, float $rate): void;

    public function controlledAccessDeniedTotal(int $tenantId): void;

    public function pdfUploadedTotal(string $status): void;
}

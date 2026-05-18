<?php

namespace App\Domain\Prescription\Pdf;

use App\Domain\Prescription\Prescription\Prescription;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * T072 — Wrapper sobre o Storage disk de PDFs de receita.
 *
 * Centraliza a lógica de path para garantir isolamento por tenant e versão.
 * Path: `prescriptions/{tenant_id}/{prescription_id}/v{version}.pdf`
 */
class PrescriptionPdfStorage
{
    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.prescriptions_disk', 's3'));
    }

    /**
     * Retorna o path versionado para armazenamento de PDF.
     */
    public function pathFor(Prescription $prescription, int $version): string
    {
        return "prescriptions/{$prescription->tenant_id}/{$prescription->id}/v{$version}.pdf";
    }

    /**
     * Verifica se um arquivo existe no disco.
     */
    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    /**
     * Grava conteúdo (stream ou string) no path indicado.
     *
     * Laravel's `put()` aceita tanto string quanto resource PHP — delega para
     * Flysystem's stream support quando um resource é passado.
     *
     * @param resource|string $contents
     */
    public function put(string $path, mixed $contents): bool
    {
        return $this->disk()->put($path, $contents);
    }

    /**
     * Gera URL assinada com TTL em minutos.
     */
    public function temporaryUrl(string $path, int $ttlMinutes = 15): string
    {
        return $this->disk()->temporaryUrl($path, now()->addMinutes($ttlMinutes));
    }
}

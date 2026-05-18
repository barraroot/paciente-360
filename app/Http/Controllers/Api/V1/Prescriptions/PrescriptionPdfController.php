<?php

namespace App\Http\Controllers\Api\V1\Prescriptions;

use App\Domain\Prescription\Pdf\PrescriptionSignedUrlService;
use App\Domain\Prescription\Prescription\Prescription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescriptions\UploadPrescriptionPdfRequest;
use App\Jobs\Prescription\PrescriptionPdfUploadJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * T069 — Controller de PDF de receita.
 *
 * Upload é assíncrono (AC-8.1.4): textuais persistidos antes do upload.
 * Download retorna URL assinada com TTL 15min (FR-033).
 */
class PrescriptionPdfController extends Controller
{
    public function __construct(
        private readonly PrescriptionSignedUrlService $signedUrlService,
    ) {}

    /**
     * POST /api/v1/prescriptions/{prescription}/pdf
     *
     * Armazena PDF temporariamente em `local` disk e dispatch o job para S3.
     * Retorna 202 Accepted — upload processado em background.
     */
    public function upload(UploadPrescriptionPdfRequest $request, Prescription $prescription): JsonResponse
    {
        $file = $request->file('pdf');
        $tmpPath = 'prescription-pdfs/'.$prescription->id.'_'.uniqid().'.pdf';

        Storage::disk('local')->put($tmpPath, $file->get());

        PrescriptionPdfUploadJob::dispatch($prescription->id, $tmpPath);

        return response()->json([
            'message' => 'PDF upload accepted and processing in background.',
            'prescription_id' => $prescription->id,
        ], 202);
    }

    /**
     * GET /api/v1/prescriptions/{prescription}/pdf
     *
     * Retorna URL assinada com TTL 15min para download do PDF.
     */
    public function download(Prescription $prescription): JsonResponse
    {
        Gate::authorize('view', $prescription);

        if ($prescription->pdf_path === null) {
            return response()->json([
                'message' => 'No PDF uploaded for this prescription.',
            ], 404);
        }

        $signed = $this->signedUrlService->sign($prescription, request()->user());

        return response()->json([
            'url' => $signed['url'],
            'expires_at' => $signed['expires_at'],
        ]);
    }
}

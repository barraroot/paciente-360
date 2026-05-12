<?php

namespace App\Http\Controllers\Api\V1\Inbox;

use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Domain\Messaging\Message\Services\MediaUploadService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\MediaUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * T250 — Controller de upload/download de mídia pré-assinada (NC-9).
 *
 * Pipeline: MediaUploadRequest → Controller (fino) → MediaUploadService.
 * Autorização delegada ao FormRequest (`inbox.respond`) e à Policy de tenant.
 */
final class MediaController extends Controller
{
    /**
     * POST /api/v1/inbox/media/upload
     *
     * Solicita URL pré-assinada PUT para upload direto ao S3.
     * Retorna `media_token` que deve ser incluído no payload da mensagem.
     */
    public function upload(MediaUploadRequest $request, MediaUploadService $service): JsonResponse
    {
        try {
            $result = $service->requestUpload(
                user: $request->user(),
                conversationId: (int) $request->integer('conversation_id'),
                mimeType: (string) $request->input('mime_type'),
                sizeBytes: (int) $request->integer('size_bytes'),
                originalFilename: $request->input('original_filename'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Os dados fornecidos são inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        Log::info('media.upload.requested', [
            'tenant_id' => $request->user()?->tenant_id,
            'user_id' => $request->user()?->id,
            'conversation_id' => $request->integer('conversation_id'),
            'mime_type' => $request->input('mime_type'),
            'size_bytes' => $request->integer('size_bytes'),
        ]);

        return response()->json($result, 201);
    }

    /**
     * GET /api/v1/inbox/media/{id}
     *
     * Retorna URL de download assinada (24h) ou 410 se mídia foi purgada.
     */
    public function show(int $id, MediaUploadService $service): JsonResponse
    {
        $user = request()->user();

        $media = MessageMedia::withoutGlobalScopes()
            ->where('id', $id)
            ->where('tenant_id', $user?->tenant_id)
            ->first();

        if ($media === null) {
            abort(404);
        }

        if ($media->media_purged_at !== null) {
            return response()->json([
                'message' => 'Mídia foi purgada e não está mais disponível.',
                'purged_at' => $media->media_purged_at->toIso8601String(),
                'placeholder' => '[mídia removida por política de retenção LGPD]',
            ], 410);
        }

        try {
            $downloadUrl = $service->signedDownloadUrl($media);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'placeholder' => '[mídia removida por política de retenção LGPD]',
            ], 410);
        }

        return response()->json([
            'download_url' => $downloadUrl,
            'expires_at' => now()->addMinutes(1440)->toIso8601String(),
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'sensitive_hint' => $media->sensitive_hint,
        ]);
    }
}

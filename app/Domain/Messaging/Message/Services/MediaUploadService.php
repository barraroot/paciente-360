<?php

namespace App\Domain\Messaging\Message\Services;

use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * T249 — Serviço de upload e download de mídia via S3 pré-assinado (NC-9).
 *
 * Fluxo de upload em duas etapas:
 * 1. Cliente solicita `requestUpload` → recebe URL pré-assinada PUT + media_token.
 * 2. Cliente faz PUT direto no S3 e, em seguida, inclui `media_token` no payload
 *    da mensagem. O controller chama `attachToMessage` para vincular.
 *
 * MIME whitelist + size limits por tipo (NC-9.a).
 * Extensões proibidas sempre rejeitadas (blocklist de executáveis).
 */
class MediaUploadService
{
    /**
     * MIME types permitidos → limite de bytes por arquivo.
     *
     * @var array<string, int>
     */
    public const MIME_LIMITS = [
        'image/jpeg' => 16_777_216,
        'image/png' => 16_777_216,
        'image/webp' => 16_777_216,
        'audio/mpeg' => 16_777_216,
        'audio/ogg' => 16_777_216,
        'audio/mp4' => 16_777_216,  // m4a
        'video/mp4' => 16_777_216,
        'application/pdf' => 104_857_600,
    ];

    /**
     * Extensões sempre bloqueadas independente do MIME type informado.
     *
     * @var array<int, string>
     */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'sh', 'ps1', 'cmd', 'scr', 'com', 'msi', 'jar', 'app',
    ];

    /**
     * TTL do media_token em minutos (1 hora).
     */
    private const TOKEN_TTL_MINUTES = 60;

    /**
     * TTL da URL de download assinada em minutos (24 horas).
     */
    private const DOWNLOAD_TTL_MINUTES = 1440;

    /**
     * Solicita URL pré-assinada para upload direto ao S3.
     *
     * Valida MIME contra whitelist e tamanho contra limite por tipo.
     * Sanitiza filename e cria registro `MessageMedia` placeholder com status `pending`.
     *
     * @return array{media_token: string, upload_url: string, upload_headers: array<string, string>, expires_at: string}
     *
     * @throws ValidationException
     */
    public function requestUpload(
        User $user,
        int $conversationId,
        string $mimeType,
        int $sizeBytes,
        ?string $originalFilename,
    ): array {
        $this->validateMimeType($mimeType);
        $this->validateSizeLimit($mimeType, $sizeBytes);

        if ($originalFilename !== null) {
            $this->validateExtension($originalFilename);
        }

        $sanitizedFilename = $this->sanitizeFilename($originalFilename ?? 'upload');
        $tenantId = $user->tenant_id;
        $uuid = Str::uuid()->toString();

        $storagePath = "tenant_{$tenantId}/conversa_{$conversationId}/upload_{$uuid}_{$sanitizedFilename}";

        $mediaToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(self::TOKEN_TTL_MINUTES);

        $media = MessageMedia::create([
            'tenant_id' => $tenantId,
            'message_id' => null, // placeholder — vinculado em attachToMessage
            'storage_disk' => 'media',
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'original_filename' => $sanitizedFilename,
            'checksum_sha256' => '',
            'sensitive_hint' => true,
            'media_purged_at' => null,
            'media_token' => $mediaToken,
            'media_token_expires_at' => $expiresAt,
        ]);

        $uploadUrl = $this->generatePresignedPutUrl($storagePath, $mimeType, $expiresAt);

        return [
            'media_token' => $mediaToken,
            'upload_url' => $uploadUrl,
            'upload_headers' => [
                'Content-Type' => $mimeType,
            ],
            'expires_at' => $expiresAt->toIso8601String(),
            'media_id' => $media->id,
        ];
    }

    /**
     * Vincula um upload pré-assinado a uma mensagem existente.
     *
     * Chamado pelo MessagesController.store quando payload tem `media_token`.
     *
     * @throws \InvalidArgumentException se o token for inválido ou expirado
     */
    public function attachToMessage(string $mediaToken, Message $message): MessageMedia
    {
        $media = MessageMedia::withoutGlobalScopes()
            ->where('media_token', $mediaToken)
            ->where('tenant_id', $message->tenant_id)
            ->whereNull('message_id')
            ->first();

        if ($media === null) {
            throw new \InvalidArgumentException('Media token inválido ou já utilizado.');
        }

        if ($media->media_token_expires_at !== null && $media->media_token_expires_at->isPast()) {
            throw new \InvalidArgumentException('Media token expirado.');
        }

        $media->update([
            'message_id' => $message->id,
            'media_token' => null,
            'media_token_expires_at' => null,
        ]);

        return $media->fresh();
    }

    /**
     * Gera URL assinada temporária para download de mídia (24h padrão).
     *
     * @throws \RuntimeException se a mídia foi purgada
     */
    public function signedDownloadUrl(MessageMedia $media, int $ttlMinutes = self::DOWNLOAD_TTL_MINUTES): string
    {
        if ($media->media_purged_at !== null) {
            throw new \RuntimeException('Mídia foi purgada e não está mais disponível.');
        }

        return Storage::disk($media->storage_disk)->temporaryUrl(
            $media->storage_path,
            now()->addMinutes($ttlMinutes),
        );
    }

    /**
     * @throws ValidationException
     */
    private function validateMimeType(string $mimeType): void
    {
        if (! array_key_exists($mimeType, self::MIME_LIMITS)) {
            throw ValidationException::withMessages([
                'mime_type' => ["MIME type '{$mimeType}' não é permitido."],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateSizeLimit(string $mimeType, int $sizeBytes): void
    {
        $limit = self::MIME_LIMITS[$mimeType] ?? 0;

        if ($sizeBytes > $limit) {
            $limitMb = round($limit / 1_048_576, 0);

            throw ValidationException::withMessages([
                'size_bytes' => ["Arquivo excede o limite de {$limitMb}MB para tipo '{$mimeType}'."],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateExtension(string $filename): void
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'original_filename' => ["Extensão '.{$ext}' não é permitida por razões de segurança."],
            ]);
        }
    }

    /**
     * Sanitiza o filename: mantém apenas alfanuméricos, pontos e traços.
     * Limite de 200 chars para deixar espaço para o prefixo do path.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove path traversal
        $filename = basename($filename);

        // Mantém apenas chars seguros
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename) ?? 'upload';

        // Remove pontos duplos (path traversal via extension)
        $filename = preg_replace('/\.{2,}/', '.', $filename) ?? $filename;

        return mb_substr($filename, 0, 200);
    }

    /**
     * Gera URL pré-assinada para PUT direto no S3.
     *
     * `temporaryUploadUrl()` retorna `[url, headers]` no Flysystem S3 e um
     * array ou string em ambientes de teste (Storage::fake). Este método
     * normaliza o retorno para sempre devolver apenas a string da URL.
     */
    private function generatePresignedPutUrl(string $storagePath, string $mimeType, Carbon $expiresAt): string
    {
        $disk = Storage::disk('media');

        // Flysystem AWS S3 v3: temporaryUploadUrl retorna [url, headers]
        if (method_exists($disk, 'temporaryUploadUrl')) {
            $result = $disk->temporaryUploadUrl(
                $storagePath,
                $expiresAt,
                ['ContentType' => $mimeType],
            );

            // Normaliza: se retornar array [url, headers], extrai a URL
            if (is_array($result)) {
                return (string) ($result[0] ?? $result['url'] ?? '');
            }

            return (string) $result;
        }

        // Fallback: URL de leitura temporária (MinIO sem suporte a presigned PUT).
        // Em produção, o disco `media` deve suportar temporaryUploadUrl.
        return (string) $disk->temporaryUrl($storagePath, $expiresAt);
    }
}

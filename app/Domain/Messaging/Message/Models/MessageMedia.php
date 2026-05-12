<?php

namespace App\Domain\Messaging\Message\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Messaging\MessageMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * T031 — Model `MessageMedia`: metadados de mídia anexada a mensagem.
 *
 * Arquivo físico em S3 (disk `media`). AES-256 SSE no bucket.
 * Retenção: 1 ano (`media_retention_months` — NC-14); `PurgeExpiredMediaJob`
 * deleta do S3 e seta `media_purged_at`.
 *
 * `sensitive_hint = true` por padrão (LGPD precaução) — admin pode reverter.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $message_id
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $original_filename
 * @property string $checksum_sha256
 * @property bool $sensitive_hint
 * @property Carbon|null $media_purged_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MessageMedia extends Model
{
    /** @use HasFactory<MessageMediaFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_message_media';

    protected static function newFactory(): MessageMediaFactory
    {
        return MessageMediaFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'message_id',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'original_filename',
        'checksum_sha256',
        'sensitive_hint',
        'media_purged_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_purged_at' => 'datetime',
            'sensitive_hint' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    // -------------------------------------------------------------------------
    // Domain methods
    // -------------------------------------------------------------------------

    /**
     * Gera URL assinada temporária para acesso à mídia pelo atendente.
     * TTL padrão: 1440 minutos (24h) — NC-14/R4.
     *
     * Implementação real via `MediaUploadService` (Lote N).
     * Aqui exposto como método de conveniência do model.
     */
    public function signedUrl(int $ttlMinutes = 1440): string
    {
        return Storage::disk($this->storage_disk)->temporaryUrl(
            $this->storage_path,
            now()->addMinutes($ttlMinutes)
        );
    }
}

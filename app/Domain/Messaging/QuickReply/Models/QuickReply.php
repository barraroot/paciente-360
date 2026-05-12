<?php

namespace App\Domain\Messaging\QuickReply\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\Messaging\QuickReplyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T032 — Model `QuickReply`: respostas rápidas com escopo dual (NC-8).
 *
 * Escopo dual:
 *  - `owner_user_id IS NULL` → compartilhada no tenant (scope tenant).
 *  - `owner_user_id NOT NULL` → privada do usuário.
 *
 * Atalho inclui a barra: ex.: `/preço`, `/horario`.
 * `has_media` sempre `false` no MVP (NC-8.d).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $owner_user_id
 * @property string $shortcut
 * @property string $content
 * @property bool $has_media
 * @property array<int, string> $variables_used
 * @property int $usage_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class QuickReply extends Model
{
    /** @use HasFactory<QuickReplyFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_quick_replies';

    protected static function newFactory(): QuickReplyFactory
    {
        return QuickReplyFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'owner_user_id',
        'shortcut',
        'content',
        'has_media',
        'variables_used',
        'usage_count',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'variables_used' => AsJsonArray::class,
            'has_media' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Respostas rápidas compartilhadas no tenant (owner_user_id IS NULL).
     */
    public function scopeTenantScope(Builder $query): Builder
    {
        return $query->whereNull('owner_user_id');
    }

    /**
     * Respostas rápidas privadas do usuário especificado.
     */
    public function scopePrivateOf(Builder $query, User $user): Builder
    {
        return $query->where('owner_user_id', $user->id);
    }
}

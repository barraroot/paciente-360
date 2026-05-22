<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Models;

use App\Casts\AsJsonArray;
use Database\Factories\SuperAdminAuditScreenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T090 (Fase 8 — Lote B US-12.1)** — Audit granular de tela visitada durante
 * sessão de impersonate (Q19 / Gate 7).
 *
 * @property int $id
 * @property int $impersonate_session_id
 * @property string $route
 * @property string $path
 * @property string $method
 * @property Carbon $visited_at
 * @property string $ip_address
 * @property array<string, mixed>|null $query_params
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ImpersonateSession $session
 */
class SuperAdminAuditScreen extends Model
{
    /** @use HasFactory<SuperAdminAuditScreenFactory> */
    use HasFactory;

    protected $table = 'super_admin_audit_screens';

    protected $fillable = [
        'impersonate_session_id',
        'route',
        'path',
        'method',
        'visited_at',
        'ip_address',
        'query_params',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'query_params' => AsJsonArray::class,
        ];
    }

    protected static function newFactory(): SuperAdminAuditScreenFactory
    {
        return SuperAdminAuditScreenFactory::new();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImpersonateSession::class, 'impersonate_session_id');
    }

    public function scopeByRoute(Builder $query, string $route): Builder
    {
        return $query->where('route', $route);
    }
}

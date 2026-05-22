<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T248 (Fase 8 — Lote E US-10.1)** — Audit de exportação CSV/PDF (FR-10.3).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $tipo
 * @property string $formato
 * @property array<string, mixed> $filters_applied
 * @property int $exported_by_user_id
 * @property Carbon $exported_at
 * @property string|null $file_path
 * @property int|null $file_size_bytes
 * @property int|null $row_count
 */
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'report_exports';

    protected $fillable = [
        'tenant_id',
        'tipo',
        'formato',
        'filters_applied',
        'exported_by_user_id',
        'exported_at',
        'file_path',
        'file_size_bytes',
        'row_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters_applied' => AsJsonArray::class,
            'exported_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ReportExportFactory
    {
        return ReportExportFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }

    public function scopeOfType(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('exported_at', '>=', Carbon::now()->subDays($days));
    }
}

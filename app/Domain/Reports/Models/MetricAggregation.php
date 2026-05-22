<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Database\Factories\MetricAggregationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T248 (Fase 8 — Lote E US-10.1)** — Pré-computação de KPI agregado (Q9).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $metric_name
 * @property MetricPeriod $period
 * @property Carbon $period_start
 * @property array<string, mixed>|null $dimensions
 * @property float|null $value_numeric
 * @property array<string, mixed>|null $value_json
 * @property Carbon $computed_at
 */
class MetricAggregation extends Model
{
    /** @use HasFactory<MetricAggregationFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'metric_aggregations';

    protected $fillable = [
        'tenant_id',
        'metric_name',
        'period',
        'period_start',
        'dimensions',
        'value_numeric',
        'value_json',
        'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => MetricPeriod::class,
            'period_start' => 'datetime',
            'dimensions' => AsJsonArray::class,
            'value_numeric' => 'float',
            'value_json' => AsJsonArray::class,
            'computed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): MetricAggregationFactory
    {
        return MetricAggregationFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForMetric(Builder $query, string $name): Builder
    {
        return $query->where('metric_name', $name);
    }

    public function scopeForPeriod(Builder $query, MetricPeriod|string $period): Builder
    {
        $value = $period instanceof MetricPeriod ? $period->value : $period;

        return $query->where('period', $value);
    }

    public function scopeBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('period_start', [$start, $end]);
    }
}

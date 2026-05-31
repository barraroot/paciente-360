<?php

declare(strict_types=1);

namespace App\Domain\Ai\Execution\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature 017 (US5, FR-031) — registro auditável de uma invocação de tool da IA.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $conversation_id
 * @property string|null $correlation_id
 * @property string $tool_name
 * @property array<string, mixed>|null $input_summary
 * @property string $outcome
 * @property array<string, mixed>|null $result_summary
 * @property int|null $latency_ms
 */
class AiToolInvocation extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'ai_tool_invocations';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'correlation_id',
        'tool_name',
        'source',
        'sandbox',
        'input_summary',
        'outcome',
        'result_summary',
        'latency_ms',
        'created_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'input_summary' => AsJsonArray::class,
            'result_summary' => AsJsonArray::class,
            'latency_ms' => 'integer',
            'sandbox' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}

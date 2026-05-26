<?php

namespace App\Domain\Ai\Execution\Models;

use App\Casts\AsJsonArray;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Ai\AiExecutionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log auditável por decisão de IA (Princípios III/V). Conteúdo já
 * pseudonimizado (sem PII clínica).
 */
class AiExecutionLog extends Model
{
    /** @use HasFactory<AiExecutionLogFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'ai_execution_logs';

    protected static function newFactory(): AiExecutionLogFactory
    {
        return AiExecutionLogFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'message_id',
        'response_message_id',
        'ai_persona_id',
        'ai_model_id',
        'channel_type',
        'correlation_id',
        'prompt_summary',
        'context_summary',
        'classified_intent',
        'confidence_score',
        'response_summary',
        'action',
        'status',
        'error_message',
        'latency_ms',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'context_summary' => AsJsonArray::class,
            'confidence_score' => 'float',
            'latency_ms' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(AiPersona::class, 'ai_persona_id');
    }
}

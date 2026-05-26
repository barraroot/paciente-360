<?php

namespace App\Domain\Ai\Distribution\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado persistente do round-robin por (tenant, canal).
 * Acesso sempre via lockForUpdate em transação (AiPersonaSelectorService).
 */
class AiChannelDistributionState extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_channel_distribution_states';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'channel_type',
        'last_ai_persona_id',
        'last_position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_position' => 'integer',
        ];
    }
}

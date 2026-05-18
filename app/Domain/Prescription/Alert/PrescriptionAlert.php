<?php

namespace App\Domain\Prescription\Alert;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionAlert extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'prescription_alerts';

    /** @var array<int, string> */
    protected $fillable = [
        'prescription_id',
        'tenant_id',
        'alert_type',
        'status',
        'scheduled_for',
        'dispatched_at',
        'channel_id',
        'message_id',
        'failure_reason',
        'skip_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alert_type' => AlertType::class,
            'status' => AlertStatus::class,
            'scheduled_for' => 'date',
            'dispatched_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}

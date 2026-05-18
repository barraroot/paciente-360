<?php

namespace App\Domain\Prescription\PrescriptionItem;

use App\Domain\Prescription\Prescription\Prescription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $table = 'prescription_items';

    /** @var array<int, string> */
    protected $fillable = [
        'prescription_id',
        'medication_name',
        'concentration',
        'pharmaceutical_form',
        'posology',
        'quantity',
        'treatment_duration',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}

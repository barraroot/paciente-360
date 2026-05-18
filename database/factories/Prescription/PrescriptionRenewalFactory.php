<?php

namespace Database\Factories\Prescription;

use App\Domain\Prescription\Renewal\InitiatedByType;
use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionRenewal>
 */
class PrescriptionRenewalFactory extends Factory
{
    protected $model = PrescriptionRenewal::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'original_prescription_id' => PrescriptionFactory::new(),
            'renewed_prescription_id' => null,
            'initiated_by' => InitiatedByType::Professional,
            'appointment_id' => null,
            'requested_by_user_id' => null,
        ];
    }

    /**
     * Renovação iniciada pelo médico.
     */
    public function byProfessional(): self
    {
        return $this->state(fn () => [
            'initiated_by' => InitiatedByType::Professional,
        ]);
    }

    /**
     * Renovação iniciada pela IA.
     */
    public function byAi(): self
    {
        return $this->state(fn () => [
            'initiated_by' => InitiatedByType::Ai,
        ]);
    }
}

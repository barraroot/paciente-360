<?php

namespace Database\Factories\Prescription;

use App\Domain\Prescription\Prescription\CancellationReasonCategory;
use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionSource;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        $issuedAt = Carbon::today();
        $durationDays = fake()->randomElement([30, 60, 90, 180]);
        $expiresAt = $issuedAt->copy()->addDays($durationDays);

        $tenantId = Tenant::factory()->create()->id;

        return [
            'tenant_id' => $tenantId,
            'patient_id' => Paciente::factory()->state(['tenant_id' => $tenantId]),
            'professional_id' => User::factory()->state(['tenant_id' => $tenantId]),
            'appointment_id' => null,
            'type' => PrescriptionType::Common,
            'status' => PrescriptionStatus::Active,
            'source' => PrescriptionSource::Manual,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'renewed_from_id' => null,
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
            'cancellation_reason_category' => null,
            'cancellation_reason' => null,
            'alert_disabled' => false,
            'notes' => null,
            'pdf_path' => null,
            'pdf_version' => 0,
            'imported_at' => null,
            'imported_source' => null,
            'historical_external_id' => null,
        ];
    }

    /**
     * Receita comum com duration preset {30,60,90,180} dias.
     */
    public function common(): self
    {
        $issuedAt = Carbon::today();
        $durationDays = fake()->randomElement([30, 60, 90, 180]);

        return $this->state(fn () => [
            'type' => PrescriptionType::Common,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays($durationDays),
        ]);
    }

    /**
     * Receita especial — validade fixa 30 dias (Portaria 344/98).
     */
    public function special(): self
    {
        $issuedAt = Carbon::today();

        return $this->state(fn () => [
            'type' => PrescriptionType::Special,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30),
        ]);
    }

    /**
     * Receita controlada — validade fixa 30 dias, exatamente 1 item.
     */
    public function controlled(): self
    {
        $issuedAt = Carbon::today();

        return $this->state(fn () => [
            'type' => PrescriptionType::Controlled,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30),
            'alert_disabled' => false,
        ]);
    }

    /**
     * Receita cancelada com motivo preenchido.
     */
    public function cancelled(): self
    {
        $issuedAt = Carbon::today()->subDays(5);

        return $this->state(fn (array $attrs) => [
            'type' => PrescriptionType::Common,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30),
            'status' => PrescriptionStatus::Cancelled,
            'cancelled_at' => Carbon::now(),
            'cancelled_by_user_id' => $attrs['professional_id'],
            'cancellation_reason_category' => CancellationReasonCategory::ErroEmissao,
            'cancellation_reason' => 'Cancelamento de teste.',
        ]);
    }

    /**
     * Receita vencida (expires_at no passado, status active).
     */
    public function expired(): self
    {
        $issuedAt = Carbon::today()->subDays(60);

        return $this->state(fn () => [
            'type' => PrescriptionType::Common,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(30),
            'status' => PrescriptionStatus::Active,
        ]);
    }

    /**
     * Vincula a um tenant específico, ajustando patient_id e professional_id
     * para pertencerem ao mesmo tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant->id,
            'patient_id' => Paciente::factory()->state(['tenant_id' => $tenant->id]),
            'professional_id' => User::factory()->state(['tenant_id' => $tenant->id]),
        ]);
    }

    /**
     * Vincula um profissional específico sem alterar o patient_id.
     */
    public function withProfessional(User $professional): self
    {
        return $this->state(fn () => ['professional_id' => $professional->id]);
    }
}

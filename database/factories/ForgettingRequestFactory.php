<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\ForgettingStatus;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T044 (Fase 8 — Lote A US-13.2)** — Factory para {@see ForgettingRequest}.
 *
 * Default: status=open, requested_at=hoje, deadline_at=hoje+15 dias úteis
 * (aproximação simples 21 dias corridos para testes).
 *
 * @extends Factory<ForgettingRequest>
 */
class ForgettingRequestFactory extends Factory
{
    protected $model = ForgettingRequest::class;

    public function definition(): array
    {
        $tenantId = Tenant::factory()->create()->id;
        $requestedAt = Carbon::now();

        return [
            'tenant_id' => $tenantId,
            'patient_id' => Paciente::factory()->state(['tenant_id' => $tenantId]),
            'requested_at' => $requestedAt,
            'deadline_at' => $requestedAt->copy()->addDays(21), // ~15 dias úteis
            'channel_of_request' => fake()->randomElement(['form', 'email', 'whatsapp', 'manual']),
            'status' => ForgettingStatus::Open,
            'executed_at' => null,
            'executed_by_user_id' => null,
            'fields_anonymized' => null,
            'fields_deleted' => null,
            'fields_preserved_reason' => null,
            'denial_reason' => null,
        ];
    }

    public function open(): self
    {
        return $this->state(fn (): array => ['status' => ForgettingStatus::Open]);
    }

    public function pendingVerification(): self
    {
        return $this->state(fn (): array => ['status' => ForgettingStatus::PendingVerification]);
    }

    public function inProgress(): self
    {
        return $this->state(fn (): array => ['status' => ForgettingStatus::InProgress]);
    }

    public function executed(?User $by = null): self
    {
        return $this->state(fn (): array => [
            'status' => ForgettingStatus::Executed,
            'executed_at' => Carbon::now(),
            'executed_by_user_id' => $by?->id ?? User::factory(),
            'fields_anonymized' => ['nome' => '***'],
            'fields_deleted' => ['endereco'],
            'fields_preserved_reason' => [
                ['field' => 'audit_logs', 'reason' => 'lgpd_art_16', 'retention_days' => 365],
            ],
        ]);
    }

    public function expired(): self
    {
        // requested_at recuado para honrar o CHECK chk_forgetting_deadline_after_request
        // (deadline_at deve ser posterior a requested_at), simulando prazo já vencido.
        return $this->state(fn (): array => [
            'status' => ForgettingStatus::Expired,
            'requested_at' => Carbon::now()->subDays(22),
            'deadline_at' => Carbon::now()->subDay(),
        ]);
    }

    public function denied(string $reason = 'Identidade não confirmada'): self
    {
        return $this->state(fn (): array => [
            'status' => ForgettingStatus::Denied,
            'denial_reason' => $reason,
        ]);
    }

    /**
     * Cria uma solicitação cujo deadline expira em N dias úteis.
     */
    public function deadlineIn(int $days): self
    {
        return $this->state(fn (): array => [
            'deadline_at' => Carbon::now()->addDays($days),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Privacy\Models\PortabilityRequest;
use App\Domain\Privacy\Models\PortabilityStatus;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * **T044 (Fase 8 — Lote A US-13.2)** — Factory para {@see PortabilityRequest}.
 *
 * @extends Factory<PortabilityRequest>
 */
class PortabilityRequestFactory extends Factory
{
    protected $model = PortabilityRequest::class;

    public function definition(): array
    {
        $tenantId = Tenant::factory()->create()->id;
        $requestedAt = Carbon::now();

        return [
            'tenant_id' => $tenantId,
            'patient_id' => Paciente::factory()->state(['tenant_id' => $tenantId]),
            'requested_at' => $requestedAt,
            'deadline_at' => $requestedAt->copy()->addDays(21),
            'status' => PortabilityStatus::Open,
            'executed_at' => null,
            'executed_by_user_id' => null,
            'file_path' => null,
            'file_size_bytes' => null,
            'file_signed_url_id' => null,
            'url_expires_at' => null,
            'downloaded_at' => null,
            'schema_version' => '1.0',
        ];
    }

    public function open(): self
    {
        return $this->state(fn (): array => ['status' => PortabilityStatus::Open]);
    }

    public function ready(?User $by = null): self
    {
        return $this->state(function (array $attrs) use ($by): array {
            $now = Carbon::now();

            return [
                'status' => PortabilityStatus::Ready,
                'executed_at' => $now,
                'executed_by_user_id' => $by?->id ?? User::factory(),
                'file_path' => 'privacy/portability/'.($attrs['patient_id'] ?? 'unknown').'/'.Str::uuid().'.json',
                'file_size_bytes' => fake()->numberBetween(1024, 524288),
                'file_signed_url_id' => Str::uuid()->toString(),
                'url_expires_at' => $now->copy()->addDays(7),
            ];
        });
    }

    public function downloaded(): self
    {
        return $this->ready()->state(fn (): array => [
            'status' => PortabilityStatus::Downloaded,
            'downloaded_at' => Carbon::now()->subHours(2),
        ]);
    }

    public function expired(): self
    {
        return $this->ready()->state(fn (): array => [
            'status' => PortabilityStatus::Expired,
            'url_expires_at' => Carbon::now()->subDay(),
        ]);
    }
}

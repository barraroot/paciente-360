<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Models\ConsentRecord;
use App\Domain\Privacy\Models\ConsentState;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T023 (Fase 8 — Lote A US-13.1)** — Factory para {@see ConsentRecord}.
 *
 * Default: state=granted, finalidade=marketing, canal=whatsapp. Use states
 * dedicados para gerar variantes:
 *   - `transacional()` — consentimento implícito
 *   - `revoked()` — historizado
 *   - `forFinalidade(...)` — sobrescreve a finalidade
 *
 * @extends Factory<ConsentRecord>
 */
class ConsentRecordFactory extends Factory
{
    protected $model = ConsentRecord::class;

    public function definition(): array
    {
        $tenantId = Tenant::factory()->create()->id;
        $grantedAt = Carbon::now()->subDays(fake()->numberBetween(0, 30));

        return [
            'tenant_id' => $tenantId,
            'patient_id' => Paciente::factory()->state(['tenant_id' => $tenantId]),
            'channel' => fake()->randomElement(['whatsapp', 'instagram', 'web', 'form']),
            'finalidade' => ConsentFinalidade::Marketing,
            'state' => ConsentState::Granted,
            'granted_at' => $grantedAt,
            'revoked_at' => null,
            'evidence_message_id' => null,
            'evidence_snapshot' => [
                'text' => 'Aceito receber comunicações da clínica.',
                'received_at' => $grantedAt->toIso8601String(),
            ],
            'terms_version' => '1.0',
            'scope' => null,
        ];
    }

    public function transacional(): self
    {
        return $this->state(fn (): array => [
            'finalidade' => ConsentFinalidade::Transacional,
        ]);
    }

    public function marketing(): self
    {
        return $this->state(fn (): array => [
            'finalidade' => ConsentFinalidade::Marketing,
        ]);
    }

    public function pesquisa(): self
    {
        return $this->state(fn (): array => [
            'finalidade' => ConsentFinalidade::Pesquisa,
        ]);
    }

    public function granted(): self
    {
        return $this->state(fn (): array => [
            'state' => ConsentState::Granted,
            'granted_at' => Carbon::now(),
            'revoked_at' => null,
        ]);
    }

    public function refused(): self
    {
        return $this->state(fn (): array => [
            'state' => ConsentState::Refused,
            'granted_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function revoked(): self
    {
        return $this->state(fn (): array => [
            'state' => ConsentState::Revoked,
            'granted_at' => Carbon::now()->subDays(30),
            'revoked_at' => Carbon::now(),
        ]);
    }

    public function forFinalidade(ConsentFinalidade $finalidade): self
    {
        return $this->state(fn (): array => [
            'finalidade' => $finalidade,
        ]);
    }
}

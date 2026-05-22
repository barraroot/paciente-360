<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Privacy\Models\PseudonymizationAudit;
use App\Domain\Privacy\Models\PseudonymizationAuditMode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * **T071 (Fase 8 — Lote A US-13.3)** — Factory para {@see PseudonymizationAudit}.
 *
 * @extends Factory<PseudonymizationAudit>
 */
class PseudonymizationAuditFactory extends Factory
{
    protected $model = PseudonymizationAudit::class;

    public function definition(): array
    {
        return [
            'audited_at' => Carbon::now()->subHours(fake()->numberBetween(1, 24 * 7)),
            'audited_by_user_id' => null, // automático default
            'mode' => PseudonymizationAuditMode::RuntimeReplay,
            'scope_event_types' => [],
            'sample_size' => 100,
            'total_events_scanned' => 100,
            'non_conformant_events' => 0,
            'findings' => null,
            'report_summary' => null,
        ];
    }

    public function staticReflection(): self
    {
        return $this->state(fn (): array => [
            'mode' => PseudonymizationAuditMode::StaticReflection,
            'sample_size' => null,
        ]);
    }

    public function runtimeReplay(): self
    {
        return $this->state(fn (): array => [
            'mode' => PseudonymizationAuditMode::RuntimeReplay,
        ]);
    }

    /**
     * Cria audit com findings — útil para testes de painel.
     *
     * @param  list<array<string, mixed>>  $findings
     */
    public function withFindings(array $findings = []): self
    {
        if ($findings === []) {
            $findings = [
                ['event_id' => 1234, 'field_path' => 'patient.cpf', 'pattern' => 'cpf', 'severity' => 'critical'],
            ];
        }

        return $this->state(fn (): array => [
            'findings' => $findings,
            'non_conformant_events' => count($findings),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Console\Command;

/**
 * **T048 (Spec 012)** — Backfill one-shot dos unlocks progressivos do onboarding.
 *
 * Tenants criados ANTES da Fase 12 não têm os triggers de unlock aplicados:
 * podem ter `clinic_data` completo com `first_professional` ainda `locked`, ou
 * `first_professional` completo com `schedule_setup` ainda `locked`.
 *
 * Este comando reaplica os unlocks via `OnboardingService::unlockStep`, que é
 * idempotente (no-op quando o step não está `locked`). Pode rodar quantas vezes
 * for necessário sem efeito colateral.
 *
 * @see specs/012-professionals-management/research.md R5
 */
final class OnboardingBackfillUnlocksCommand extends Command
{
    protected $signature = 'onboarding:backfill-unlocks {--dry-run : Apenas reporta o que seria desbloqueado, sem persistir}';

    protected $description = 'Reaplica os unlocks progressivos do onboarding (Fase 12) em tenants existentes.';

    public function __construct(private readonly OnboardingService $onboarding)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $unlocked = 0;

        Tenant::query()->chunkById(200, function ($tenants) use ($dryRun, &$unlocked): void {
            foreach ($tenants as $tenant) {
                foreach ($this->stepsToUnlock($tenant) as $stepKey) {
                    $unlocked++;

                    if ($dryRun) {
                        $this->line("[dry-run] tenant {$tenant->id} ({$tenant->slug}) → unlock '{$stepKey}'");

                        continue;
                    }

                    $this->onboarding->unlockStep($tenant, $stepKey);
                    $this->line("tenant {$tenant->id} ({$tenant->slug}) → unlock '{$stepKey}'");
                }
            }
        });

        $verb = $dryRun ? 'seriam desbloqueados' : 'desbloqueados';
        $this->info("Backfill concluído: {$unlocked} step(s) {$verb}.");

        return self::SUCCESS;
    }

    /**
     * Determina quais steps precisam de unlock para um tenant, com base no
     * estado persistido. Retorna apenas os que ainda estão `locked`.
     *
     * @return list<string>
     */
    private function stepsToUnlock(Tenant $tenant): array
    {
        $state = is_array($tenant->onboarding_state) ? $tenant->onboarding_state : [];
        $steps = $state['steps'] ?? [];

        $statusOf = static fn (string $key): string => $steps[$key]['status']
            ?? OnboardingService::STEPS[$key]['status'];

        $result = [];

        if ($statusOf('clinic_data') === 'completed' && $statusOf('first_professional') === 'locked') {
            $result[] = 'first_professional';
        }

        if ($statusOf('first_professional') === 'completed' && $statusOf('schedule_setup') === 'locked') {
            $result[] = 'schedule_setup';
        }

        return $result;
    }
}

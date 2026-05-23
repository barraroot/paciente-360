<?php

namespace App\Services\Onboarding;

use App\Events\Onboarding\OnboardingStepCompleted;
use App\Events\Onboarding\OnboardingStepSkipped;
use App\Exceptions\Onboarding\StepLockedException;
use App\Exceptions\Onboarding\StepRequiredException;
use App\Exceptions\Onboarding\UnknownStepException;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Regras de negócio do wizard de onboarding do tenant.
 *
 * As 5 etapas são definidas em `STEPS`. Apenas `clinic_data` começa
 * desbloqueada nesta fase; as demais ficam `locked` até regras futuras
 * as desbloquearem. O progresso é calculado sobre as etapas `required`.
 *
 * Concorrência: `completeStep` usa `lockForUpdate` dentro de transação
 * para evitar double-complete em requisições simultâneas.
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § OnboardingStateResource
 */
final class OnboardingService
{
    /**
     * Definição canônica das 5 etapas do wizard.
     * `status` é o valor de inicialização — pode ser sobrescrito pelo estado
     * persistido no `onboarding_state` do tenant.
     *
     * @var array<string, array{label_key: string, required: bool, status: string}>
     */
    public const STEPS = [
        'clinic_data' => [
            'label_key' => 'tenant.onboarding.steps.clinic_data',
            'required' => true,
            'status' => 'pending',
        ],
        'first_professional' => [
            'label_key' => 'tenant.onboarding.steps.first_professional',
            'required' => false,
            'status' => 'locked',
        ],
        'channel_connection' => [
            'label_key' => 'tenant.onboarding.steps.channel_connection',
            'required' => false,
            'status' => 'locked',
        ],
        'schedule_setup' => [
            'label_key' => 'tenant.onboarding.steps.schedule_setup',
            'required' => false,
            'status' => 'locked',
        ],
        'ai_knowledge_base' => [
            'label_key' => 'tenant.onboarding.steps.ai_knowledge_base',
            'required' => false,
            'status' => 'locked',
        ],
    ];

    /**
     * Retorna o estado atual do wizard para o tenant.
     *
     * Se `onboarding_state` estiver vazio, inicializa com a estrutura default.
     * Faz merge com `STEPS` para garantir forward-compat quando novas etapas
     * forem adicionadas em fases futuras.
     *
     * @return array{completed: bool, progress_percent: int, steps: list<array{key: string, label: string, status: string, required: bool}>}
     */
    public function getState(Tenant $tenant): array
    {
        $persistedState = is_array($tenant->onboarding_state) ? $tenant->onboarding_state : [];
        $persistedSteps = $persistedState['steps'] ?? [];

        $steps = [];

        foreach (self::STEPS as $key => $definition) {
            $persisted = $persistedSteps[$key] ?? [];

            $steps[] = [
                'key' => $key,
                'label' => __($definition['label_key']),
                'status' => $persisted['status'] ?? $definition['status'],
                'required' => $definition['required'],
            ];
        }

        $totalRequired = count(array_filter(self::STEPS, fn ($d) => $d['required']));
        $completedRequired = count(array_filter($steps, fn ($s) => $s['required'] && $s['status'] === 'completed'));

        $progressPercent = $totalRequired > 0
            ? (int) round($completedRequired / $totalRequired * 100)
            : 0;

        $allCompleted = $totalRequired > 0 && $completedRequired === $totalRequired;

        return [
            'completed' => $allCompleted,
            'progress_percent' => $progressPercent,
            'steps' => $steps,
        ];
    }

    /**
     * Marca um step como completado e persiste o estado no tenant.
     *
     * Usa `lockForUpdate` em transação para evitar concorrência.
     * Dispara `OnboardingStepCompleted` (auditável).
     *
     * @param array<string, mixed> $payload
     * @return array{completed: bool, progress_percent: int, steps: list<array{key: string, label: string, status: string, required: bool}>}
     *
     * @throws UnknownStepException quando `$stepKey` não existe.
     * @throws StepLockedException quando o step está `locked`.
     */
    public function completeStep(Tenant $tenant, string $stepKey, array $payload): array
    {
        if (! array_key_exists($stepKey, self::STEPS)) {
            throw new UnknownStepException($stepKey);
        }

        return DB::transaction(function () use ($tenant, $stepKey, $payload): array {
            /** @var Tenant $fresh */
            $fresh = Tenant::lockForUpdate()->findOrFail($tenant->id);

            $persistedState = is_array($fresh->onboarding_state) ? $fresh->onboarding_state : [];
            $persistedSteps = $persistedState['steps'] ?? [];

            $currentStatus = $persistedSteps[$stepKey]['status'] ?? self::STEPS[$stepKey]['status'];

            if ($currentStatus === 'locked') {
                throw new StepLockedException($stepKey);
            }

            $persistedSteps[$stepKey] = [
                'status' => 'completed',
                'completed_at' => now()->toIso8601String(),
                'payload' => $payload,
            ];

            $fresh->onboarding_state = [
                'steps' => $persistedSteps,
                'completed' => $this->computeCompleted($persistedSteps),
            ];
            $fresh->save();

            Event::dispatch(new OnboardingStepCompleted($fresh, $stepKey));

            return $this->getState($fresh);
        });
    }

    /**
     * Marca um step opcional como pulado e persiste o estado no tenant.
     *
     * Dispara `OnboardingStepSkipped` (auditável).
     *
     * @return array{completed: bool, progress_percent: int, steps: list<array{key: string, label: string, status: string, required: bool}>}
     *
     * @throws UnknownStepException quando `$stepKey` não existe.
     * @throws StepRequiredException quando o step é `required`.
     * @throws StepLockedException quando o step está `locked`.
     */
    public function skipStep(Tenant $tenant, string $stepKey): array
    {
        if (! array_key_exists($stepKey, self::STEPS)) {
            throw new UnknownStepException($stepKey);
        }

        $definition = self::STEPS[$stepKey];

        if ($definition['required']) {
            throw new StepRequiredException($stepKey);
        }

        return DB::transaction(function () use ($tenant, $stepKey): array {
            /** @var Tenant $fresh */
            $fresh = Tenant::lockForUpdate()->findOrFail($tenant->id);

            $persistedState = is_array($fresh->onboarding_state) ? $fresh->onboarding_state : [];
            $persistedSteps = $persistedState['steps'] ?? [];

            $currentStatus = $persistedSteps[$stepKey]['status'] ?? self::STEPS[$stepKey]['status'];

            if ($currentStatus === 'locked') {
                throw new StepLockedException($stepKey);
            }

            $persistedSteps[$stepKey] = [
                'status' => 'skipped',
                'skipped_at' => now()->toIso8601String(),
            ];

            $fresh->onboarding_state = [
                'steps' => $persistedSteps,
                'completed' => $this->computeCompleted($persistedSteps),
            ];
            $fresh->save();

            Event::dispatch(new OnboardingStepSkipped($fresh, $stepKey));

            return $this->getState($fresh);
        });
    }

    /**
     * Verifica se todos os steps `required` estão `completed`.
     *
     * @param array<string, array{status: string}> $persistedSteps
     */
    private function computeCompleted(array $persistedSteps): bool
    {
        foreach (self::STEPS as $key => $definition) {
            if (! $definition['required']) {
                continue;
            }

            $status = $persistedSteps[$key]['status'] ?? $definition['status'];

            if ($status !== 'completed') {
                return false;
            }
        }

        return true;
    }
}

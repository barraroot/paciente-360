<?php

namespace App\Services\Billing;

use App\Models\Plan;
use InvalidArgumentException;

/**
 * Gerencia o catálogo de planos comerciais (T284).
 *
 * Centraliza validações e operações sobre planos para que o
 * `PlanResource` do Filament delegue aqui sem duplicar regras.
 */
class PlanService
{
    /**
     * Cria um novo plano no catálogo.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Plan
    {
        $this->validatePrices($data);

        return Plan::create($data);
    }

    /**
     * Atualiza um plano existente.
     *
     * @param array<string, mixed> $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $this->validatePrices($data);

        $plan->update($data);

        return $plan->refresh();
    }

    /**
     * Alterna o campo `is_active` do plano.
     */
    public function toggleActive(Plan $plan): Plan
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return $plan->refresh();
    }

    /**
     * Valida que os campos de preço, quando presentes, são >= 0.
     *
     * @param array<string, mixed> $data
     */
    private function validatePrices(array $data): void
    {
        $priceFields = ['base_price_cents', 'overage_price_cents'];

        foreach ($priceFields as $field) {
            if (array_key_exists($field, $data) && (int) $data[$field] < 0) {
                throw new InvalidArgumentException(
                    "O campo [{$field}] não pode ser negativo."
                );
            }
        }
    }
}

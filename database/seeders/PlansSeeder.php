<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * **T063** — Seeder de planos default (`starter`, `pro`, `enterprise`).
 *
 * Stripe Price IDs são lidos de env. Em produção, ENVs ausentes lançam
 * `RuntimeException` (fail-fast — Princípio VII). Em dev/test, fallback
 * `price_xxx_dev` é aceito.
 *
 * Idempotente via `DB::table('plans')->updateOrInsert(['code' => …], …)`.
 *
 * Schema canônico em `data-model.md` § 2:
 *   id, code, name, description, base_price_cents, included_professionals,
 *   included_ai_messages, overage_price_cents, max_users, max_channels,
 *   stripe_price_id_base, stripe_price_id_overage, is_active, timestamps.
 */
class PlansSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const PLANS = [
        [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Plano inicial para clínicas pequenas — 1 profissional, 5 usuários.',
            'base_price_cents' => 19900,
            'included_professionals' => 1,
            'max_users' => 5,
            'max_channels' => 1,
            'included_ai_messages' => 10000,
            'overage_price_cents' => 5,
            'stripe_env_base' => 'STRIPE_PRICE_BASE_STARTER',
        ],
        [
            'code' => 'pro',
            'name' => 'Pro',
            'description' => 'Plano profissional — 5 profissionais, 20 usuários, 50k mensagens IA.',
            'base_price_cents' => 49900,
            'included_professionals' => 5,
            'max_users' => 20,
            'max_channels' => 3,
            'included_ai_messages' => 50000,
            'overage_price_cents' => 5,
            'stripe_env_base' => 'STRIPE_PRICE_BASE_PRO',
        ],
        [
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Plano enterprise — 20 profissionais, 100 usuários, 200k mensagens IA.',
            'base_price_cents' => 149900,
            'included_professionals' => 20,
            'max_users' => 100,
            'max_channels' => 10,
            'included_ai_messages' => 200000,
            'overage_price_cents' => 5,
            'stripe_env_base' => 'STRIPE_PRICE_BASE_ENTERPRISE',
        ],
    ];

    public function run(): void
    {
        $isProduction = app()->environment('production');

        $overagePriceId = $this->resolveStripePriceId(
            'STRIPE_PRICE_AI_OVERAGE',
            $isProduction,
            'price_ai_overage_dev'
        );

        $now = now();

        foreach (self::PLANS as $plan) {
            $basePriceId = $this->resolveStripePriceId(
                $plan['stripe_env_base'],
                $isProduction,
                'price_'.$plan['code'].'_base_dev'
            );

            DB::table('plans')->updateOrInsert(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'base_price_cents' => $plan['base_price_cents'],
                    'included_professionals' => $plan['included_professionals'],
                    'included_ai_messages' => $plan['included_ai_messages'],
                    'overage_price_cents' => $plan['overage_price_cents'],
                    'max_users' => $plan['max_users'],
                    'max_channels' => $plan['max_channels'],
                    'stripe_price_id_base' => $basePriceId,
                    'stripe_price_id_overage' => $overagePriceId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Resolve um `stripe_price_id` a partir de env. Em produção, falta de
     * env é fatal. Em dev/test, retorna o fallback determinístico.
     */
    private function resolveStripePriceId(string $envKey, bool $isProduction, string $fallback): string
    {
        $value = env($envKey);

        if (! is_string($value) || $value === '') {
            if ($isProduction) {
                throw new RuntimeException(
                    "Env [{$envKey}] é obrigatória em produção para `PlansSeeder`."
                );
            }

            return $fallback;
        }

        return $value;
    }
}

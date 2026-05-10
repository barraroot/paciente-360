<?php

namespace Tests\Feature\Fase0\Seeders;

use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * **T063** — verifica `PlansSeeder`: 3 planos com códigos canônicos,
 * idempotência (no-op no reseed) e fail-fast em produção sem envs.
 */
class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_three_default_plans(): void
    {
        Artisan::call('db:seed', [
            '--class' => PlansSeeder::class,
            '--force' => true,
        ]);

        $codes = DB::table('plans')
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame(['enterprise', 'pro', 'starter'], $codes);
    }

    public function test_seeding_is_idempotent(): void
    {
        Artisan::call('db:seed', [
            '--class' => PlansSeeder::class,
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => PlansSeeder::class,
            '--force' => true,
        ]);

        $this->assertSame(3, DB::table('plans')->count());
    }

    public function test_seeding_in_production_without_env_throws(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        // Garante envs vazias (configuradas em phpunit.xml + .env de teste).
        putenv('STRIPE_PRICE_BASE_STARTER');
        putenv('STRIPE_PRICE_BASE_PRO');
        putenv('STRIPE_PRICE_BASE_ENTERPRISE');
        putenv('STRIPE_PRICE_AI_OVERAGE');

        $this->expectException(RuntimeException::class);

        (new PlansSeeder)->run();
    }
}

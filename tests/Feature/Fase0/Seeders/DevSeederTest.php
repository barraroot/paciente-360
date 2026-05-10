<?php

namespace Tests\Feature\Fase0\Seeders;

use App\Models\Tenant;
use Database\Seeders\DevSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **T065** — verifica `DevSeeder`: 2 tenants navegáveis (`clinica-alfa`
 * com 5 usuários e 5 roles diferentes; `clinica-beta` em trial com 1
 * admin user).
 */
class DevSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_seeder_creates_two_tenants(): void
    {
        Artisan::call('db:seed', [
            '--class' => DevSeeder::class,
            '--force' => true,
        ]);

        $tenants = Tenant::query()->orderBy('slug')->pluck('slug')->all();
        $this->assertSame(['clinica-alfa', 'clinica-beta'], $tenants);
    }

    public function test_clinica_alfa_has_five_users_with_distinct_roles(): void
    {
        Artisan::call('db:seed', [
            '--class' => DevSeeder::class,
            '--force' => true,
        ]);

        $alfa = Tenant::query()->where('slug', 'clinica-alfa')->firstOrFail();

        $userCount = DB::table('users')->where('tenant_id', $alfa->id)->count();
        $this->assertSame(5, $userCount, 'clinica-alfa deve ter 5 usuários.');

        $roleNames = DB::table('roles')
            ->where('tenant_id', $alfa->id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['admin-clinica', 'atendente', 'financeiro', 'medico', 'recepcionista'],
            $roleNames,
            'clinica-alfa deve ter as 5 roles template clonadas.'
        );

        // Cada user deve ter exatamente 1 role atribuída no contexto do tenant.
        $assignmentsCount = DB::table('model_has_roles')
            ->where('tenant_id', $alfa->id)
            ->count();

        $this->assertSame(5, $assignmentsCount, 'Cada user de clinica-alfa deve ter 1 role atribuída.');
    }

    public function test_clinica_beta_has_one_user_in_trial(): void
    {
        Artisan::call('db:seed', [
            '--class' => DevSeeder::class,
            '--force' => true,
        ]);

        $beta = Tenant::query()->where('slug', 'clinica-beta')->firstOrFail();

        $this->assertSame('trial', $beta->status);
        $this->assertNotNull($beta->trial_ends_at);

        $userCount = DB::table('users')->where('tenant_id', $beta->id)->count();
        $this->assertSame(1, $userCount, 'clinica-beta deve ter apenas 1 admin user.');
    }

    public function test_clinica_alfa_has_subscription_and_professional(): void
    {
        Artisan::call('db:seed', [
            '--class' => DevSeeder::class,
            '--force' => true,
        ]);

        $alfa = Tenant::query()->where('slug', 'clinica-alfa')->firstOrFail();

        $subscription = DB::table('subscriptions')->where('tenant_id', $alfa->id)->first();
        $this->assertNotNull($subscription, 'clinica-alfa deve ter assinatura starter contratada.');
        $this->assertSame('active', $subscription->stripe_status);
        $this->assertSame('sub_dev_alfa', $subscription->stripe_id);

        $professionals = DB::table('professionals')->where('tenant_id', $alfa->id)->count();
        $this->assertSame(1, $professionals, 'clinica-alfa deve ter 1 profissional vinculado.');
    }
}

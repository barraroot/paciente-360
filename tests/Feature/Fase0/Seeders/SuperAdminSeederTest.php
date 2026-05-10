<?php

namespace Tests\Feature\Fase0\Seeders;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

/**
 * **T064** — verifica `SuperAdminSeeder`: cria User com tenant_id NULL,
 * vincula role `super-admin`, idempotente, fail-fast em produção sem env.
 */
class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_user_is_created_with_null_tenant(): void
    {
        Artisan::call('db:seed', [
            '--class' => RolesSeeder::class,
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => SuperAdminSeeder::class,
            '--force' => true,
        ]);

        $user = User::query()->where('email', 'super@admin.local')->first();

        $this->assertNotNull($user, 'Super Admin deve existir após o seed.');
        $this->assertNull($user->tenant_id, 'Super Admin deve ter tenant_id NULL.');
        $this->assertTrue($user->hasRole('super-admin'));
    }

    public function test_seeding_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);

        Artisan::call('db:seed', ['--class' => SuperAdminSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => SuperAdminSeeder::class, '--force' => true]);

        $count = User::query()->where('email', 'super@admin.local')->count();
        $this->assertSame(1, $count, 'Reseed do Super Admin não pode duplicar.');
    }

    public function test_seeding_in_production_without_env_throws(): void
    {
        Artisan::call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);

        $this->app->detectEnvironment(fn (): string => 'production');

        putenv('SUPER_ADMIN_EMAIL');
        putenv('SUPER_ADMIN_PASSWORD');

        $this->expectException(RuntimeException::class);

        (new SuperAdminSeeder)->run();
    }
}

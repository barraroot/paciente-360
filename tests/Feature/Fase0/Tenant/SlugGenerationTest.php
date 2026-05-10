<?php

namespace Tests\Feature\Fase0\Tenant;

use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * **T142** — geração e validação de slug do tenant (FR-005, RNF — RFC 1035).
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § TenantRegisterRequest
 * @see specs/001-fundacao-multitenant/tasks.md — T142
 */
class SlugGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_URL = 'http://crm.lvh.me/api/v1/tenants/register';

    /** @var list<array{cnpj: string, email: string}> */
    private array $cnpjPool = [
        ['cnpj' => '11.222.333/0001-81', 'email' => 'p1@clinica.com'],
        ['cnpj' => '04.252.011/0001-10', 'email' => 'p2@clinica.com'],
        ['cnpj' => '11.444.777/0001-61', 'email' => 'p3@clinica.com'],
        ['cnpj' => '33.014.556/0001-96', 'email' => 'p4@clinica.com'],
        ['cnpj' => '12.345.678/0001-95', 'email' => 'p5@clinica.com'],
        ['cnpj' => '98.765.432/0001-98', 'email' => 'p6@clinica.com'],
        ['cnpj' => '00.123.456/0001-49', 'email' => 'p7@clinica.com'],
        ['cnpj' => '55.667.788/0001-86', 'email' => 'p8@clinica.com'],
    ];

    private int $cnpjIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, PlansSeeder::class]);
        $this->clearTenantRegisterLimiter();
        Notification::fake();
    }

    /**
     * Reseta o limiter `tenant-register` para o IP de teste (`127.0.0.1`).
     *
     * O middleware `throttle:tenant-register` (Laravel 13) deriva a chave
     * via `md5('tenant-register' . $request->ip())` — um simples
     * `RateLimiter::clear('tenant-register')` não bate na chave real.
     */
    private function clearTenantRegisterLimiter(): void
    {
        RateLimiter::clear(md5('tenant-register'.'127.0.0.1'));
        // Fallback: limpa também o nome direto (caso `shouldHashKeys` seja
        // desativado em algum ambiente).
        RateLimiter::clear('tenant-register:127.0.0.1');
        // E o cache subjacente onde o ThrottleRequests grava o timer:
        Cache::forget(md5('tenant-register'.'127.0.0.1').':timer');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $next = $this->cnpjPool[$this->cnpjIndex++] ?? null;

        $base = [
            'clinic_name' => 'Clínica Foo',
            'cnpj' => $next['cnpj'] ?? '11.222.333/0001-81',
            'slug' => null,
            'responsible_name' => 'Dr. Foo Silva',
            'responsible_email' => $next['email'] ?? 'admin@clinica.com',
            'responsible_phone' => '+5511999999999',
            'password' => 'StrongPass123',
            'terms_accepted' => true,
            'terms_version' => '1.0',
        ];

        return array_replace($base, $overrides);
    }

    public function test_slug_normalized_lowercase_ascii(): void
    {
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'clinic_name' => 'Clínica Saúde Total',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('tenant.slug', 'clinica-saude-total');
    }

    public function test_slug_collision_appends_numeric_suffix(): void
    {
        // 1ª clínica chamada "Clinica X" → slug `clinica-x`.
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'clinic_name' => 'Clinica X',
        ]))->assertCreated()->assertJsonPath('tenant.slug', 'clinica-x');

        // 2ª clínica com mesmo nome → slug `clinica-x-2`.
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'clinic_name' => 'Clinica X',
        ]))->assertCreated()->assertJsonPath('tenant.slug', 'clinica-x-2');

        // 3ª clínica → slug `clinica-x-3`.
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'clinic_name' => 'Clinica X',
        ]))->assertCreated()->assertJsonPath('tenant.slug', 'clinica-x-3');
    }

    public function test_reserved_slugs_rejected(): void
    {
        $reserved = array_unique(array_merge(
            (array) config('tenancy.reserved_slugs'),
            (array) config('tenancy.public_hosts'),
        ));

        $expectedMessage = __('tenant.register.slug_reserved');

        foreach ($reserved as $slug) {
            // Limiter `tenant-register` é 3/h por IP. Como este teste itera
            // sobre vários slugs reservados (cada hit conta), precisamos
            // resetar antes de cada request.
            $this->clearTenantRegisterLimiter();

            $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
                'slug' => $slug,
            ]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['slug']);
            $errors = $response->json('errors.slug');
            $this->assertIsArray($errors);
            $this->assertContains(
                $expectedMessage,
                $errors,
                "Slug reservado [{$slug}] não retornou a mensagem [{$expectedMessage}]: ".json_encode($errors),
            );
        }
    }

    public function test_slug_format_validated_rfc1035(): void
    {
        $invalid = ['Clinica_X', '1clinica', 'cl', '-clinica', 'clinica-'];
        $expectedMessage = __('tenant.register.slug_invalid');

        foreach ($invalid as $slug) {
            $this->clearTenantRegisterLimiter();

            $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
                'slug' => $slug,
            ]));

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['slug']);
            $errors = $response->json('errors.slug');
            $this->assertContains(
                $expectedMessage,
                $errors,
                "Slug inválido [{$slug}] deveria retornar a mensagem [{$expectedMessage}]: ".json_encode($errors),
            );
        }
    }

    public function test_slug_max_length_63(): void
    {
        // 64 caracteres — excede limite RFC 1035 (max 63).
        $tooLong = 'c'.str_repeat('a', 63);

        $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'slug' => $tooLong,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }
}

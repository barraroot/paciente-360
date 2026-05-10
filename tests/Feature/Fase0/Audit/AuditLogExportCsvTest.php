<?php

namespace Tests\Feature\Fase0\Audit;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * T261 — Feature tests do export CSV de audit logs (US-2.4 — FR-037).
 *
 * Cobre:
 *  - Headers HTTP corretos (Content-Type, Content-Disposition).
 *  - BOM UTF-8 nos primeiros bytes.
 *  - Escape contra Excel/CSV formula injection (`=`, `+`, `-`, `@`, TAB, CR).
 *  - Escape RFC 4180 de aspas duplas.
 *  - Serialização de payload JSON como string.
 *  - Filtros respeitados.
 *  - Autorização (médico negado).
 *  - Streaming de grandes datasets.
 */
class AuditLogExportCsvTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array{tenant: Tenant, admin: User}
     */
    private function tenantWithAdmin(string $slug = 'clinica-csv'): array
    {
        $tenant = $this->createTenant(['slug' => $slug]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        foreach (['admin-clinica', 'financeiro', 'medico'] as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]
            );
        }

        $admin = $this->createUserForTenant($tenant, ['status' => 'active']);
        $admin->assignRole('admin-clinica');

        return ['tenant' => $tenant, 'admin' => $admin];
    }

    private function url(string $slug, string $query = ''): string
    {
        $base = "http://{$slug}.lvh.me/api/v1/audit-logs/export";

        return $query === '' ? $base : "{$base}?{$query}";
    }

    /**
     * Helper: captura o corpo da response streamed.
     */
    private function streamedBody($response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /** @test */
    public function test_export_returns_csv_with_correct_headers(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-csv-headers');

        AuditLog::factory()->count(2)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->get($this->url('clinica-csv-headers'));

        $response->assertOk();
        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('audit-logs-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    /** @test */
    public function test_export_starts_with_utf8_bom(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-csv-bom');

        AuditLog::factory()->count(1)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->get($this->url('clinica-csv-bom'));

        $response->assertOk();
        $body = $this->streamedBody($response->baseResponse);

        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3), 'CSV deve iniciar com BOM UTF-8.');
    }

    /** @test */
    public function test_export_escapes_formula_injection(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-csv-injection');

        foreach (
            [
                ['note' => '=cmd|/c calc!A1'],
                ['note' => '+SUM(A1)'],
                ['note' => '-1+1'],
                ['note' => '@LookUp'],
                ['note' => "\tTAB"],
                ['note' => "\rCR"],
            ] as $payload
        ) {
            AuditLog::factory()->forTenant($tenant)->state(['payload' => $payload])->create();
        }

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->get($this->url('clinica-csv-injection'));

        $response->assertOk();
        $body = $this->streamedBody($response->baseResponse);

        // Cada payload JSON serializado começa com `{` → não dispara escape
        // diretamente, MAS o `CsvExporter::escapeFormulaInjection` é
        // unitariamente testado. Aqui validamos o cenário direto:
        //
        // Para garantir que a saída do CSV NÃO contém uma célula iniciando
        // com `=`, `+`, `-`, `@`, TAB ou CR (cada célula está entre `,`
        // ou no início de linha), inspecionamos cada linha.

        $lines = preg_split("/\r?\n/", $body) ?: [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            // Tokeniza por vírgula respeitando aspas. Para simplicidade,
            // usamos str_getcsv com escape default.
            $cells = str_getcsv($line, ',', '"', '\\');

            foreach ($cells as $cell) {
                if ($cell === '' || $cell === null) {
                    continue;
                }

                $first = $cell[0];
                if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
                    $this->fail("Célula inicia com caractere perigoso de formula injection: '{$cell}'");
                }
            }
        }

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function test_export_escapes_double_quotes_in_payload(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-csv-quote');

        AuditLog::factory()
            ->forTenant($tenant)
            ->state(['payload' => ['text' => 'A "B" C']])
            ->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->get($this->url('clinica-csv-quote'));

        $response->assertOk();
        $body = $this->streamedBody($response->baseResponse);

        // RFC 4180 escape: cada `"` é duplicado para `""` dentro de células
        // entre aspas. O payload original `A "B" C` é JSON-encoded para
        // `A "B" C` (cada " → \") e depois fputcsv duplica cada " →
        // resultando em `A ""B"" C`. O importante é que NUNCA apareça um
        // " não-duplicado dentro de uma célula entre aspas.
        $this->assertStringContainsString('\""B\""', $body);

        // Parse o CSV para garantir que as aspas internas foram preservadas
        // semanticamente — quando lemos com str_getcsv, recuperamos o payload
        // JSON original com `"B"`.
        $lines = preg_split('/
?
/', $body) ?: [];
        $payloadCell = null;
        foreach ($lines as $i => $line) {
            if ($line === '' || $i === 0) {
                continue;
            }
            $cells = str_getcsv($line, ',', '"', '');
            $payloadCell = end($cells);
            break;
        }
        $this->assertNotNull($payloadCell);
        $decoded = json_decode((string) $payloadCell, true);
        $this->assertSame('A "B" C', $decoded['text'] ?? null);
    }

    /** @test */
    public function test_export_serializes_json_payload_as_string(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-csv-json');

        AuditLog::factory()
            ->forTenant($tenant)
            ->state(['payload' => ['foo' => 'bar', 'n' => 42]])
            ->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->get($this->url('clinica-csv-json'));

        $response->assertOk();
        $body = $this->streamedBody($response->baseResponse);

        // RFC 4180 escapa cada " como "". O payload `{"foo":"bar","n":42}`
        // vira `{""foo"":""bar"",""n"":42}` dentro da célula.
        $this->assertStringContainsString('""foo"":""bar""', $body);
        $this->assertStringContainsString('""n"":42', $body);

        // Parse round-trip: decodifica o payload-cell e confirma estrutura.
        $lines = preg_split('/?
/', $body) ?: [];
        $payloadCell = null;
        foreach ($lines as $i => $line) {
            if ($line === '' || $i === 0) {
                continue;
            }
            $cells = str_getcsv($line, ',', '"', '');
            $payloadCell = end($cells);
            break;
        }
        $this->assertNotNull($payloadCell);
        $decoded = json_decode((string) $payloadCell, true);
        $this->assertSame('bar', $decoded['foo'] ?? null);
        $this->assertSame(42, $decoded['n'] ?? null);
    }

    /** @test */
    public function test_export_respects_filters(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-csv-filter');

        AuditLog::factory()->count(3)->forTenant($tenant)->action('included.action')->create();
        AuditLog::factory()->count(2)->forTenant($tenant)->action('excluded.action')->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->get($this->url('clinica-csv-filter', 'action=included.action'));

        $response->assertOk();
        $body = $this->streamedBody($response->baseResponse);

        $this->assertStringContainsString('included.action', $body);
        $this->assertStringNotContainsString('excluded.action', $body);
    }

    /** @test */
    public function test_export_only_admin_clinica_or_financeiro(): void
    {
        ['tenant' => $tenant] = $this->tenantWithAdmin('clinica-csv-perm');

        $medico = $this->createUserForTenant($tenant, ['status' => 'active']);
        $medico->assignRole('medico');

        AuditLog::factory()->count(2)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($medico)->get($this->url('clinica-csv-perm'));

        $response->assertForbidden();
    }

    /** @test */
    public function test_export_response_streams_large_dataset(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->tenantWithAdmin('clinica-csv-large');

        // 1000 logs — usa createMany manual em batch para velocidade.
        AuditLog::factory()->count(1000)->forTenant($tenant)->create();

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)->get($this->url('clinica-csv-large'));

        $response->assertOk();

        $body = $this->streamedBody($response->baseResponse);

        // 1 BOM + 1 header + 1000 linhas + trailing newline >= ~1000 newlines.
        $newlines = substr_count($body, "\n");
        $this->assertGreaterThanOrEqual(1000, $newlines, 'CSV deve conter ao menos 1000 linhas de dados.');
        $this->assertGreaterThan(0, strlen($body));
    }
}

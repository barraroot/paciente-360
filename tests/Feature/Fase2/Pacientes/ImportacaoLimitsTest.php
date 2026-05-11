<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T171 — Testes de limites e formatos de importação (AC-3.3.3).
 */
class ImportacaoLimitsTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('imports');
        Bus::fake();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_arquivo_acima_5_mb_rejeitado(): void
    {
        // Gera conteúdo > 5MB
        $content = str_repeat('a', 5 * 1024 * 1024 + 1024);
        $file = UploadedFile::fake()->createWithContent('grande.csv', $content);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $file,
            'status_inicial' => 'lead',
        ]);

        $this->assertContains($response->status(), [413, 422]);

        if ($response->status() === 422) {
            $errors = $response->json('errors') ?? [];
            $allErrors = implode(' ', array_merge(...array_values($errors)));
            $this->assertStringContainsStringIgnoringCase('arquivo', $allErrors);
        }
    }

    /** @test */
    public function test_arquivo_acima_10000_linhas_rejeitado(): void
    {
        $headers = "nome,cpf,email\n";
        $rows = '';
        for ($i = 1; $i <= 10001; $i++) {
            $rows .= "Paciente {$i},,pac{$i}@ex.com\n";
        }

        $content = $headers.$rows;
        $file = UploadedFile::fake()->createWithContent('muitas_linhas.csv', $content);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $file,
            'status_inicial' => 'lead',
        ]);

        $this->assertContains($response->status(), [413, 422]);
    }

    /** @test */
    public function test_formato_nao_suportado_rejeitado(): void
    {
        $file = UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 content');

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $file,
            'status_inicial' => 'lead',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('arquivo');
    }

    /** @test */
    public function test_cabecalho_invalido_rejeitado(): void
    {
        // CSV sem coluna 'nome' (obrigatória)
        $csv = "cpf,email\n12345678909,test@ex.com\n";
        $file = UploadedFile::fake()->createWithContent('sem_nome.csv', $csv);

        $response = $this->postJson($this->baseUrl('/pacientes/importacao'), [
            'arquivo' => $file,
            'status_inicial' => 'lead',
        ]);

        // Cabeçalho validado sincronamente → 422
        $response->assertStatus(422);
    }
}

<?php

namespace Tests\Unit\Services\Audit;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Services\Audit\AuditAttributesBuilder;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * T033 — Cobre a mascarização de CPF em `AuditAttributesBuilder::sanitizePayload`.
 *
 * Princípio I (LGPD): CPF nunca é gravado em claro em `audit_logs`.
 * Máscara canônica: `***.***.***-XX` com os 2 últimos dígitos preservados.
 */
class AuditAttributesBuilderCpfMaskTest extends TestCase
{
    private function event(array $payload): Auditable
    {
        return new class($payload) implements Auditable
        {
            use IsAuditable;

            public function __construct(private readonly array $payloadData) {}

            public function auditAction(): string
            {
                return 'test.cpf.mask';
            }

            public function auditPayload(): array
            {
                return $this->payloadData;
            }

            public function auditableModel(): ?Model
            {
                return null;
            }

            public function auditTenantId(): ?int
            {
                return null;
            }

            public function auditUserId(): ?int
            {
                return null;
            }
        };
    }

    private function build(array $payload): array
    {
        $builder = new AuditAttributesBuilder;

        return $builder->build($this->event($payload))['payload'];
    }

    /** @test */
    public function test_masks_plain_cpf_key(): void
    {
        $payload = $this->build(['cpf' => '12345678909']);

        $this->assertSame('***.***.***-09', $payload['cpf']);
    }

    /** @test */
    public function test_masks_formatted_cpf(): void
    {
        $payload = $this->build(['documento_cpf' => '999.888.777-66']);

        $this->assertSame('***.***.***-66', $payload['documento_cpf']);
    }

    /** @test */
    public function test_masks_nested_cpf_recursively(): void
    {
        $payload = $this->build([
            'user' => [
                'cpf' => '111',
            ],
            'meta' => ['inner' => ['cpf_titular' => '22222222222']],
        ]);

        $this->assertSame('***.***.***-11', $payload['user']['cpf']);
        $this->assertSame('***.***.***-22', $payload['meta']['inner']['cpf_titular']);
    }

    /** @test */
    public function test_null_cpf_is_preserved(): void
    {
        $payload = $this->build(['cpf' => null]);

        $this->assertArrayHasKey('cpf', $payload);
        $this->assertNull($payload['cpf']);
    }

    /** @test */
    public function test_non_cpf_keys_untouched(): void
    {
        $payload = $this->build([
            'nome' => 'João da Silva',
            'texto' => 'observação genérica',
        ]);

        $this->assertSame('João da Silva', $payload['nome']);
        $this->assertSame('observação genérica', $payload['texto']);
    }

    /** @test */
    public function test_short_cpf_falls_back_to_double_asterisk(): void
    {
        // 0 dígitos disponíveis → fallback `**`.
        $this->assertSame('***.***.***-**', $this->build(['cpf' => ''])['cpf']);
        $this->assertSame('***.***.***-**', $this->build(['cpf' => 'abc'])['cpf']);
        $this->assertSame('***.***.***-**', $this->build(['cpf' => '5'])['cpf']);
    }

    /** @test */
    public function test_password_keys_still_removed(): void
    {
        $payload = $this->build([
            'password' => 'secret',
            'cpf' => '12345678909',
        ]);

        $this->assertArrayNotHasKey('password', $payload);
        $this->assertSame('***.***.***-09', $payload['cpf']);
    }
}

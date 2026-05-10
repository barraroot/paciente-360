<?php

namespace Tests\Unit\Support;

use App\Support\Csv\CsvExporter;
use Tests\TestCase;

/**
 * T263 — Unit tests do escape contra CSV/Excel formula injection.
 *
 * Cobre os 6 caracteres do vetor OWASP CSV Injection + casos null/empty/normal.
 *
 * @see App\Support\Csv\CsvExporter
 */
class CsvExporterTest extends TestCase
{
    private CsvExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new CsvExporter;
    }

    /** @test */
    public function test_prefixes_equals_sign(): void
    {
        $this->assertSame("'=cmd", $this->exporter->escapeFormulaInjection('=cmd'));
    }

    /** @test */
    public function test_prefixes_plus_sign(): void
    {
        $this->assertSame("'+1", $this->exporter->escapeFormulaInjection('+1'));
    }

    /** @test */
    public function test_prefixes_minus_sign(): void
    {
        $this->assertSame("'-1", $this->exporter->escapeFormulaInjection('-1'));
    }

    /** @test */
    public function test_prefixes_at_sign(): void
    {
        $this->assertSame("'@something", $this->exporter->escapeFormulaInjection('@something'));
    }

    /** @test */
    public function test_prefixes_tab_char(): void
    {
        $this->assertSame("'\tfoo", $this->exporter->escapeFormulaInjection("\tfoo"));
    }

    /** @test */
    public function test_prefixes_carriage_return(): void
    {
        $this->assertSame("'\rbar", $this->exporter->escapeFormulaInjection("\rbar"));
    }

    /** @test */
    public function test_does_not_prefix_normal_string(): void
    {
        $this->assertSame('normal', $this->exporter->escapeFormulaInjection('normal'));
        $this->assertSame('hello world', $this->exporter->escapeFormulaInjection('hello world'));
        $this->assertSame('123', $this->exporter->escapeFormulaInjection('123'));
    }

    /** @test */
    public function test_null_returns_empty_string(): void
    {
        $this->assertSame('', $this->exporter->escapeFormulaInjection(null));
    }

    /** @test */
    public function test_empty_string_returns_empty_string(): void
    {
        $this->assertSame('', $this->exporter->escapeFormulaInjection(''));
    }

    /** @test */
    public function test_does_not_prefix_dangerous_char_in_middle(): void
    {
        // O escape só age no primeiro caractere — string como `A=B` é segura.
        $this->assertSame('A=B', $this->exporter->escapeFormulaInjection('A=B'));
        $this->assertSame('A+B', $this->exporter->escapeFormulaInjection('A+B'));
    }
}

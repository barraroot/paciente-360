<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\QuickReply\Services\VariableSubstitutor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * T233 — Unit test para VariableSubstitutor (puro, sem framework HTTP).
 *
 * Testa substituição das 6 variáveis suportadas (NC-8.b):
 *  - {nome_paciente}
 *  - {primeiro_nome_paciente}
 *  - {nome_profissional}
 *  - {nome_clinica}
 *  - {nome_atendente}
 *  - {data_proxima_consulta}
 */
class VariableSubstitutorTest extends TestCase
{
    private VariableSubstitutor $substitutor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->substitutor = new VariableSubstitutor;
    }

    #[Test]
    public function substitutes_single_variable_in_content(): void
    {
        $result = $this->substitutor->substitute(
            'Olá {nome_paciente}!',
            ['nome_paciente' => 'Ana']
        );

        $this->assertEquals('Olá Ana!', $result);
    }

    #[Test]
    public function substitutes_multiple_variables(): void
    {
        $result = $this->substitutor->substitute(
            'Olá {primeiro_nome_paciente}, sou {nome_atendente} da {nome_clinica}.',
            [
                'primeiro_nome_paciente' => 'Maria',
                'nome_atendente' => 'João',
                'nome_clinica' => 'Clínica Alfa',
            ]
        );

        $this->assertEquals('Olá Maria, sou João da Clínica Alfa.', $result);
    }

    #[Test]
    public function renders_unknown_variable_as_empty_string(): void
    {
        $result = $this->substitutor->substitute(
            'Valor: {variavel_inexistente}.',
            []
        );

        $this->assertEquals('Valor: .', $result);
    }

    #[Test]
    public function handles_null_context_values_gracefully(): void
    {
        $result = $this->substitutor->substitute(
            'Médico: {nome_profissional}.',
            ['nome_profissional' => null]
        );

        $this->assertEquals('Médico: .', $result);
    }

    #[Test]
    public function returns_unchanged_when_no_variables_present(): void
    {
        $template = 'Texto sem variáveis aqui.';

        $result = $this->substitutor->substitute($template, [
            'nome_paciente' => 'Ana',
        ]);

        $this->assertEquals($template, $result);
    }

    #[Test]
    public function substitutes_all_6_variables_listed_in_spec(): void
    {
        $template = '{nome_paciente}|{primeiro_nome_paciente}|{nome_profissional}|{nome_clinica}|{nome_atendente}|{data_proxima_consulta}';

        $context = [
            'nome_paciente' => 'Ana Lima',
            'primeiro_nome_paciente' => 'Ana',
            'nome_profissional' => 'Dr. Pedro',
            'nome_clinica' => 'Clínica Beta',
            'nome_atendente' => 'Carlos',
            'data_proxima_consulta' => '',
        ];

        $result = $this->substitutor->substitute($template, $context);

        $this->assertEquals('Ana Lima|Ana|Dr. Pedro|Clínica Beta|Carlos|', $result);
    }

    #[Test]
    public function substitutes_same_variable_appearing_multiple_times(): void
    {
        $result = $this->substitutor->substitute(
            '{nome_atendente} aqui, {nome_atendente} falando.',
            ['nome_atendente' => 'João']
        );

        $this->assertEquals('João aqui, João falando.', $result);
    }
}

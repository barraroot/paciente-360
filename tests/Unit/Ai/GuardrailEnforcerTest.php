<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Services\AiGuardrailEnforcer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Princípio III — defesa determinística e cobertura de bypass (T048).
 */
class GuardrailEnforcerTest extends TestCase
{
    private AiGuardrailEnforcer $enforcer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enforcer = new AiGuardrailEnforcer;
    }

    private function aiOutput(array $overrides = []): array
    {
        return array_merge([
            'resposta' => 'Claro, posso ajudar com isso.',
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
            'needs_human' => false,
        ], $overrides);
    }

    #[Test]
    public function it_sends_when_within_guardrails(): void
    {
        $decision = $this->enforcer->evaluate($this->aiOutput());

        $this->assertTrue($decision->shouldSend);
        $this->assertSame('sent', $decision->action);
        $this->assertSame('Claro, posso ajudar com isso.', $decision->text);
    }

    #[Test]
    public function it_blocks_diagnosis_intent_even_with_confident_answer(): void
    {
        // Cenário de prompt-injection: o modelo "responde" um diagnóstico com alta
        // confiança, mas a intenção classificada é clínica → NÃO envia.
        $decision = $this->enforcer->evaluate($this->aiOutput([
            'intencao' => 'diagnostico',
            'resposta' => 'Você tem gripe, tome X.',
            'confidence' => 0.99,
        ]));

        $this->assertFalse($decision->shouldSend);
        $this->assertSame('redirected_scheduling', $decision->action);
        $this->assertNull($decision->text);
    }

    #[Test]
    public function it_blocks_prescription_and_posology(): void
    {
        foreach (['prescricao', 'posologia', 'interpretacao_exame', 'conduta_risco'] as $intent) {
            $decision = $this->enforcer->evaluate($this->aiOutput(['intencao' => $intent]));
            $this->assertFalse($decision->shouldSend, "intent {$intent} deveria bloquear");
        }
    }

    #[Test]
    public function it_escalates_urgency_with_high_priority(): void
    {
        $decision = $this->enforcer->evaluate($this->aiOutput(['intencao' => 'urgencia']));

        $this->assertFalse($decision->shouldSend);
        $this->assertSame('escalated_human', $decision->action);
        $this->assertTrue($decision->highPriority);
    }

    #[Test]
    public function it_escalates_on_low_confidence(): void
    {
        $decision = $this->enforcer->evaluate($this->aiOutput(['confidence' => 0.3]));

        $this->assertFalse($decision->shouldSend);
        $this->assertSame('low_confidence', $decision->reason);
    }

    #[Test]
    public function it_escalates_when_needs_human(): void
    {
        $decision = $this->enforcer->evaluate($this->aiOutput(['needs_human' => true]));

        $this->assertFalse($decision->shouldSend);
        $this->assertTrue($decision->escalate);
    }

    #[Test]
    public function minimal_guardrails_are_always_present_even_without_clinic_guardrails(): void
    {
        $persona = new AiPersona([
            'markdown_content' => '# Persona de teste',
            'tone' => 'cordial',
        ]);
        $persona->setRelation('guardrails', new Collection);

        $instructions = $this->enforcer->composeInstructions($persona);

        $this->assertStringContainsString('diagnóstico', $instructions);
        $this->assertStringContainsString('emergência', $instructions);
        $this->assertStringContainsString('Persona de teste', $instructions);
    }
}

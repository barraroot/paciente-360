<?php

declare(strict_types=1);

namespace Tests\Feature\Constitutional;

use App\Support\Lgpd\ContainsNoClinicalData;
use ReflectionClass;
use Tests\TestCase;

/**
 * **T012** — Gate constitucional de CI (Princípio I + III).
 *
 * Valida que TODOS os eventos listados em `config('finalization.ai_consumed_events')`
 * implementam a marker interface {@see ContainsNoClinicalData}.
 *
 * Sem este gate, a fase IA futura poderia consumir um evento novo que carrega
 * PII clínica e vazá-la ao LLM. Esta é a **defesa estática** em design-time
 * complementada pela auditoria runtime semanal (Q29 — `privacy:audit-pseudonymization-weekly`).
 *
 * **Como adicionar um novo evento ao escopo IA**:
 *   1. Implementar `ContainsNoClinicalData` no evento.
 *   2. Documentar a allowlist de propriedades (espelhar pattern Fase 7).
 *   3. Adicionar o FQCN do evento em `config/finalization.php` → `ai_consumed_events`.
 *   4. Este teste passa a validar a conformidade automaticamente.
 *
 * **Por que não fazer reflection sobre todos os Events do projeto?** Porque
 * alguns eventos carregam PII deliberadamente (ex.: `ConsentimentoRegistrado`
 * carrega `evidence_message_id` que aponta para mensagem com PII). A whitelist
 * explícita força revisão consciente.
 *
 * @see specs/008-finalizacao-mvp/research.md §1 Q29
 * @see specs/008-finalizacao-mvp/plan.md §3 Gate 4
 */
class EventsForAiPseudonymizationTest extends TestCase
{
    public function test_ai_consumed_events_config_exists(): void
    {
        $events = config('finalization.ai_consumed_events');

        $this->assertIsArray(
            $events,
            'Config finalization.ai_consumed_events must be an array (mesmo que vazia).',
        );
    }

    /**
     * @dataProvider aiConsumedEventsProvider
     */
    public function test_event_implements_contains_no_clinical_data(string $eventClass): void
    {
        $this->assertTrue(
            class_exists($eventClass),
            "Evento {$eventClass} listado em ai_consumed_events não existe. ".
            'Atualize config/finalization.php ou crie a classe.',
        );

        $reflection = new ReflectionClass($eventClass);

        $this->assertTrue(
            $reflection->implementsInterface(ContainsNoClinicalData::class),
            "Evento {$eventClass} é consumido pela IA mas NÃO implementa ".
            ContainsNoClinicalData::class.'. '.
            'Adicione "implements ContainsNoClinicalData" ou remova de finalization.ai_consumed_events. '.
            'Gate Princípio I/III — sem este marker, payload pode vazar PII clínica ao LLM.',
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public static function aiConsumedEventsProvider(): array
    {
        $events = config('finalization.ai_consumed_events', []);

        // PHPUnit exige ≥1 item para data provider — quando lista vazia, retorna
        // um caso "noop" que apenas valida que a config existe (test acima).
        if (! is_array($events) || $events === []) {
            return ['noop_empty_config' => ['stdClass']];
        }

        $cases = [];
        foreach ($events as $event) {
            $cases[$event] = [$event];
        }

        return $cases;
    }

    public function test_noop_when_no_ai_consumers_registered(): void
    {
        // Documenta o comportamento esperado quando a fase IA ainda não está
        // registrada. Este teste passa intencionalmente e serve como âncora
        // operacional: quando primeiro evento for adicionado a
        // finalization.ai_consumed_events, o data provider acima começa a rodar.
        $events = config('finalization.ai_consumed_events', []);

        if ($events === []) {
            $this->assertTrue(true, 'Sem eventos IA registrados — fase IA ainda não entregue.');

            return;
        }

        // Quando há eventos registrados, este teste vira complemento ao provider.
        $this->assertGreaterThan(
            0,
            count($events),
            'Quando ai_consumed_events tem itens, eles devem estar no formato lista de FQCN.',
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Listeners\BroadcastDomainEventToWebhooksListener;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * **T210 (Fase 8 — Lote D US-11.1)** — Catálogo Q17 cobre 13 eventos.
 *
 * Validação estática: o array `EVENT_CATALOG` no listener deve listar
 * exatamente 13 eventos (Q17).
 */
class WebhookCatalogCoverageTest extends TestCase
{
    public function test_event_catalog_has_thirteen_events(): void
    {
        $ref = new ReflectionClass(BroadcastDomainEventToWebhooksListener::class);
        $catalog = $ref->getConstant('EVENT_CATALOG');

        $this->assertIsArray($catalog);
        $this->assertCount(13, $catalog, 'Catálogo Q17 deve ter EXATAMENTE 13 eventos.');
    }

    public function test_event_types_follow_dot_notation(): void
    {
        $ref = new ReflectionClass(BroadcastDomainEventToWebhooksListener::class);
        $catalog = $ref->getConstant('EVENT_CATALOG');

        foreach ($catalog as $eventClass => $eventType) {
            $this->assertMatchesRegularExpression(
                '/^[a-z_]+\.[a-z_]+$/',
                $eventType,
                "Evento {$eventClass} deve seguir formato '<recurso>.<acao>'",
            );
        }
    }

    public function test_event_classes_exist(): void
    {
        $ref = new ReflectionClass(BroadcastDomainEventToWebhooksListener::class);
        $catalog = $ref->getConstant('EVENT_CATALOG');

        foreach (array_keys($catalog) as $eventClass) {
            $this->assertTrue(
                class_exists($eventClass),
                "Classe {$eventClass} do catálogo Q17 não existe.",
            );
        }
    }
}

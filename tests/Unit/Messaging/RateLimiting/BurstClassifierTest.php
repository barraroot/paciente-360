<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\RateLimiting;

use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\RateLimiting\BurstClassifier;
use Tests\TestCase;

/**
 * **T204 (Fase 18 — Polish, FR-008d)** — unit test do classificador heurístico
 * de burst. Estende Tests\TestCase para ter app booted (encrypter está
 * registrado — `Message.body` tem cast `encrypted`).
 *
 * Valida apenas que rótulos óbvios funcionam; é heurística leve, não um
 * classifier perfeito.
 */
final class BurstClassifierTest extends TestCase
{
    public function test_identical_messages_classified_as_spam(): void
    {
        $classifier = new BurstClassifier;

        $msgs = $this->buildMessages([
            'oi quero agendar',
            'oi quero agendar',
            'oi quero agendar',
            'oi quero agendar',
            'oi quero agendar',
        ]);

        $this->assertEquals('spam', $classifier->classify($msgs));
    }

    public function test_crisis_keywords_with_caps_classified_as_crisis(): void
    {
        $classifier = new BurstClassifier;

        $msgs = $this->buildMessages([
            'preciso de URGENTE',
            'SOCORRO POR FAVOR',
            'estou em desespero alguem me ajuda',
        ]);

        $this->assertEquals('crisis', $classifier->classify($msgs));
    }

    public function test_short_sample_returns_unknown(): void
    {
        $classifier = new BurstClassifier;

        $msgs = $this->buildMessages(['oi', 'tudo bem']);

        $this->assertEquals('unknown', $classifier->classify($msgs));
    }

    public function test_normal_diverse_messages_return_unknown(): void
    {
        $classifier = new BurstClassifier;

        $msgs = $this->buildMessages([
            'quanto custa a consulta de ortopedia',
            'consigo agendar para sexta a tarde',
            'voces aceitam pix',
            'qual o endereco da clinica',
        ]);

        $this->assertEquals('unknown', $classifier->classify($msgs));
    }

    /**
     * @param array<int, string> $bodies
     * @return array<int, Message>
     */
    private function buildMessages(array $bodies): array
    {
        return array_map(function (string $body): Message {
            $m = new Message;
            $m->body = $body;

            return $m;
        }, $bodies);
    }
}

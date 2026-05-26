<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Domain\Ai\Services\AiEmbeddingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * US4 / T060 — chunking determinístico (por seções + janela com overlap).
 */
final class ChunkingTest extends TestCase
{
    private AiEmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiEmbeddingService;
        config()->set('ai.matricial.rag.chunk_chars', 200);
        config()->set('ai.matricial.rag.chunk_overlap', 50);
    }

    #[Test]
    public function it_returns_empty_for_blank_content(): void
    {
        $this->assertSame([], $this->service->chunk('   '));
        $this->assertSame([], $this->service->chunk(''));
    }

    #[Test]
    public function it_splits_by_markdown_sections(): void
    {
        $markdown = "# Horários\n\nSeg a Sex, 8h às 18h.\n\n# Estacionamento\n\nGratuito no local.";

        $chunks = $this->service->chunk($markdown);

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('Horários', $chunks[0]['content']);
        $this->assertStringContainsString('Estacionamento', $chunks[1]['content']);
        $this->assertSame([0, 1], array_column($chunks, 'index'));
    }

    #[Test]
    public function it_windows_long_sections_with_overlap_deterministically(): void
    {
        $body = str_repeat('A', 500); // seção única, > chunk_chars (200)

        $first = $this->service->chunk($body);
        $second = $this->service->chunk($body);

        // Determinístico: mesma entrada → mesma saída.
        $this->assertEquals($first, $second);

        // step = 200 - 50 = 150 → janelas em 0, 150, 300 (cobre 300–499) = 3 chunks.
        $this->assertCount(3, $first);
        $this->assertSame([0, 1, 2], array_column($first, 'index'));
        $this->assertSame(200, mb_strlen($first[0]['content']));
        $this->assertGreaterThan(0, $first[0]['token_count']);
    }

    #[Test]
    public function chunk_indices_are_sequential_across_sections(): void
    {
        $longSection = str_repeat('palavra ', 60); // ~480 chars
        $markdown = "# Um\n\n{$longSection}\n\n# Dois\n\nCurto.";

        $chunks = $this->service->chunk($markdown);

        $this->assertSame(range(0, count($chunks) - 1), array_column($chunks, 'index'));
    }
}

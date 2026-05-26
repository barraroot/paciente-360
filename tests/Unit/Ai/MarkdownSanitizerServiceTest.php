<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\Services\MarkdownSanitizerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarkdownSanitizerServiceTest extends TestCase
{
    private MarkdownSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new MarkdownSanitizerService;
    }

    #[Test]
    public function it_removes_script_blocks(): void
    {
        $input = "# Título\n\n<script>alert('x')</script>\n\nTexto.";
        $out = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert', $out);
        $this->assertStringContainsString('# Título', $out);
    }

    #[Test]
    public function it_strips_raw_html_tags_but_keeps_markdown(): void
    {
        $input = "## Sub\n\n<b>negrito</b> e **markdown** e [link](https://ex.com).";
        $out = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<b>', $out);
        $this->assertStringContainsString('**markdown**', $out);
        $this->assertStringContainsString('[link](https://ex.com)', $out);
        $this->assertStringContainsString('## Sub', $out);
    }

    #[Test]
    public function it_neutralizes_javascript_urls_in_links(): void
    {
        $input = '[clique](javascript:alert(1)) e ![img](data:text/html,xxx)';
        $out = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('data:', $out);
    }

    #[Test]
    public function it_removes_inline_event_handlers(): void
    {
        $input = '<img src=x onerror="alert(1)">';
        $out = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('alert', $out);
    }

    #[Test]
    public function it_detects_unsafe_content(): void
    {
        $this->assertTrue($this->sanitizer->containsUnsafe('<script>x</script>'));
        $this->assertFalse($this->sanitizer->containsUnsafe("# Seguro\n\nTexto **ok**."));
    }

    #[Test]
    public function it_leaves_safe_markdown_unchanged(): void
    {
        $safe = "# Título\n\n## Sub\n\n- item 1\n- [ ] tarefa\n\n> citação\n\n[link](https://ex.com)\n\n| a | b |\n|---|---|\n| 1 | 2 |";
        $this->assertSame($safe, $this->sanitizer->sanitize($safe));
    }
}

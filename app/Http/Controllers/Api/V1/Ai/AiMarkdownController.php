<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Domain\Ai\Services\MarkdownSanitizerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ValidateMarkdownRequest;
use Illuminate\Http\JsonResponse;

/**
 * US8 / G12 — validação/sanitização de Markdown no back-end (FR-041, Princípio VII).
 *
 * O preview no front usa DOMPurify (defesa em profundidade), mas a fonte de
 * verdade da segurança é o back-end: aqui devolvemos a versão sanitizada e um
 * aviso quando havia conteúdo inseguro.
 */
class AiMarkdownController extends Controller
{
    public function __construct(private readonly MarkdownSanitizerService $sanitizer) {}

    public function validate(ValidateMarkdownRequest $request): JsonResponse
    {
        $content = (string) $request->validated('content');
        $sanitized = $this->sanitizer->sanitize($content);
        $hasUnsafe = $sanitized !== $content;

        return response()->json([
            'data' => [
                'sanitized' => $sanitized,
                'has_unsafe' => $hasUnsafe,
                'warnings' => $hasUnsafe ? [__('ai.markdown.unsafe_removed')] : [],
            ],
        ]);
    }
}

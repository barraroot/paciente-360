<?php

declare(strict_types=1);

namespace App\Domain\Messaging\RateLimiting;

use App\Domain\Messaging\Message\Models\Message;
use Illuminate\Support\Collection;

/**
 * **T204 (Fase 18 — Polish, FR-008d)** — heurística simples para rotular o
 * burst que disparou o cooldown. Usado SÓ para enriquecer o alerta ao
 * operador (`burst_label` no evento `ConversationCooldownStarted`); NÃO
 * altera comportamento.
 *
 * Rótulos:
 *   - `spam`    — alta similaridade entre mensagens (Levenshtein normalizado < 0.3)
 *                 indica copia-e-cola / bot / loop;
 *   - `crisis`  — frequência sustentada de msgs longas/distintas com palavras
 *                 emocionais ou em CAPS sugere ansiedade aguda — operador deve
 *                 priorizar contato humano;
 *   - `unknown` — não classifica (default seguro).
 *
 * É de propósito SIMPLES. Aprimorar exige ML real — fora de escopo.
 */
final class BurstClassifier
{
    /** Limiar de similaridade média para rotular como spam (0–1). */
    private const SPAM_SIMILARITY_THRESHOLD = 0.3;

    /** Palavras-gatilho que sugerem crise emocional aguda. */
    private const CRISIS_KEYWORDS = [
        'urgente', 'urgencia', 'emergencia', 'socorro', 'ajuda',
        'desespero', 'desesperada', 'desesperado',
        'agora', 'imediato', 'rapido', 'pelo amor',
    ];

    /**
     * @param Collection<int, Message>|array<int, Message> $messages
     */
    public function classify(iterable $messages): string
    {
        $msgs = collect($messages)
            ->map(fn (Message $m): string => trim((string) $m->body))
            ->filter(fn (string $b): bool => $b !== '')
            ->values();

        if ($msgs->count() < 3) {
            return 'unknown';
        }

        if ($this->isLikelySpam($msgs)) {
            return 'spam';
        }

        if ($this->isLikelyCrisis($msgs)) {
            return 'crisis';
        }

        return 'unknown';
    }

    /**
     * @param Collection<int, string> $bodies
     */
    private function isLikelySpam(Collection $bodies): bool
    {
        $pairs = 0;
        $similaritySum = 0.0;

        $list = $bodies->all();
        $n = count($list);
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $pairs++;
                $a = mb_strtolower($list[$i]);
                $b = mb_strtolower($list[$j]);
                $maxLen = max(mb_strlen($a), mb_strlen($b));
                if ($maxLen === 0) {
                    continue;
                }
                // levenshtein é byte-based; suficiente para textos curtos em pt-BR.
                $distance = levenshtein(
                    substr($a, 0, 255),
                    substr($b, 0, 255),
                );
                $similaritySum += $distance / max($maxLen, 1);
            }
        }

        if ($pairs === 0) {
            return false;
        }

        $avgNormalizedDistance = $similaritySum / $pairs;

        return $avgNormalizedDistance < self::SPAM_SIMILARITY_THRESHOLD;
    }

    /**
     * @param Collection<int, string> $bodies
     */
    private function isLikelyCrisis(Collection $bodies): bool
    {
        $hits = 0;
        $caps = 0;
        foreach ($bodies as $body) {
            $lower = mb_strtolower($body);
            foreach (self::CRISIS_KEYWORDS as $kw) {
                if (str_contains($lower, $kw)) {
                    $hits++;
                    break;
                }
            }
            // Heurística complementar — proporção alta de CAPS sugere afetação.
            $letters = preg_replace('/[^a-zA-ZÀ-ÿ]/u', '', $body) ?? '';
            if (mb_strlen($letters) > 8) {
                $upper = preg_replace('/[^A-ZÀ-Ý]/u', '', $body) ?? '';
                if (mb_strlen($upper) / mb_strlen($letters) >= 0.7) {
                    $caps++;
                }
            }
        }

        return ($hits + $caps) >= 2;
    }
}

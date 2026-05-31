<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Inbound\Services;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;

/**
 * **T145 (Fase 18 — US5, FR-031/033, Q3=A)** — detecta preferência por
 * resposta em áudio a partir de gatilhos explícitos do paciente.
 *
 * Gatilhos PT-BR (`config('messaging.audio.outbound.triggers')`) — match por
 * substring após normalização (lowercase + sem acento). É por TURNO: se nas
 * últimas N mensagens inbound houve gatilho, retorna true. Quando o paciente
 * volta a digitar texto sem sinalizar, a próxima detecção retorna false
 * (sem persistência de preferência — FR-033).
 *
 * Janela default: últimas 5 mensagens inbound (cobre coalescência típica
 * + uma rodada de troca; restritivo o bastante para não "lembrar para sempre").
 */
final class AudioPreferenceDetector
{
    private const LOOKBACK_MESSAGES = 5;

    public function prefersAudioForConversation(Conversation $conversation): bool
    {
        $triggers = (array) config('messaging.audio.outbound.triggers', []);
        if ($triggers === []) {
            return false;
        }

        $recentInbound = Message::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'in')
            ->where('sender_type', 'patient')
            ->orderByDesc('id')
            ->limit(self::LOOKBACK_MESSAGES)
            ->get(['id', 'body']);

        foreach ($recentInbound as $msg) {
            if ($this->containsTrigger((string) $msg->body, $triggers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $triggers
     */
    private function containsTrigger(string $body, array $triggers): bool
    {
        if ($body === '') {
            return false;
        }
        $normalized = $this->normalize($body);

        foreach ($triggers as $trigger) {
            $needle = $this->normalize((string) $trigger);
            if ($needle === '') {
                continue;
            }
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        // Remove acentos (mantém apenas ASCII + espaço + alfanumérico).
        $stripped = iconv('UTF-8', 'ASCII//TRANSLIT', $lower);
        $stripped = $stripped === false ? $lower : (string) $stripped;
        // Normaliza espaços e remove pontuação (preserva letras/dígitos/espaços).
        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $stripped) ?? $stripped;

        return trim(preg_replace('/\s+/', ' ', $stripped) ?? $stripped);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Outbound\Services;

/**
 * **T144 (Fase 18 — US5, FR-035)** — normaliza texto para fala antes do TTS.
 *
 * Converte formatos numéricos/abreviações para pronúncia natural em PT-BR:
 *   - "R$ 300,00"      → "trezentos reais"
 *   - "R$ 99,90"       → "noventa e nove reais e noventa centavos"
 *   - "14h30" / "14:30" → "duas e meia da tarde"
 *   - "9h"             → "nove da manhã"
 *   - "Dr." / "Dra."   → "doutor" / "doutora"
 *   - "Sr." / "Sra."   → "senhor" / "senhora"
 *   - "WhatsApp"       → "uatzap"
 *
 * Cobertura conservadora — em casos não-cobertos, mantém o texto original
 * (Whisper/ElevenLabs interpretam razoavelmente bem palavras comuns).
 */
final class TtsTextNormalizer
{
    public function normalize(string $text): string
    {
        $out = $text;

        // R$ valores (R$ X,YY ou R$ X)
        $out = preg_replace_callback(
            '/R\$\s*(\d{1,3}(?:\.\d{3})*)(?:,(\d{2}))?/u',
            fn (array $m): string => $this->reaisToWords(
                (int) str_replace('.', '', $m[1]),
                isset($m[2]) ? (int) $m[2] : 0,
            ),
            $out,
        ) ?? $out;

        // Horários "14h30" ou "14:30"
        $out = preg_replace_callback(
            '/\b(\d{1,2})(?:h(\d{2})?|:(\d{2}))/u',
            fn (array $m): string => $this->timeToWords(
                (int) $m[1],
                (int) ($m[2] ?? $m[3] ?? 0),
            ),
            $out,
        ) ?? $out;

        // Abreviações comuns (ordem importa — "Dra." antes de "Dr.")
        $abbreviations = [
            '/\bDra\.\s*/u' => 'doutora ',
            '/\bDr\.\s*/u' => 'doutor ',
            '/\bSra\.\s*/u' => 'senhora ',
            '/\bSr\.\s*/u' => 'senhor ',
            '/\bWhatsApp\b/u' => 'uatzap',
            '/\bex\.\s*/iu' => 'por exemplo, ',
        ];

        foreach ($abbreviations as $pattern => $replacement) {
            $out = preg_replace($pattern, $replacement, $out) ?? $out;
        }

        return $out;
    }

    private function reaisToWords(int $reais, int $centavos): string
    {
        if ($reais === 0 && $centavos === 0) {
            return 'zero reais';
        }

        $parts = [];
        if ($reais > 0) {
            $parts[] = $this->intToWords($reais).' '.($reais === 1 ? 'real' : 'reais');
        }
        if ($centavos > 0) {
            $parts[] = $this->intToWords($centavos).' '.($centavos === 1 ? 'centavo' : 'centavos');
        }

        return implode(' e ', $parts);
    }

    private function timeToWords(int $hour, int $minute): string
    {
        $period = match (true) {
            $hour >= 0 && $hour < 6 => 'da madrugada',
            $hour >= 6 && $hour < 12 => 'da manhã',
            $hour === 12 => 'em ponto',
            $hour > 12 && $hour < 18 => 'da tarde',
            default => 'da noite',
        };

        $h12 = $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour);
        $hourWord = $this->hourToWord($h12);

        if ($minute === 0) {
            return "{$hourWord} {$period}";
        }
        if ($minute === 30) {
            return "{$hourWord} e meia {$period}";
        }
        if ($minute === 15) {
            return "{$hourWord} e quinze {$period}";
        }

        return "{$hourWord} e {$this->intToWords($minute)} {$period}";
    }

    private function hourToWord(int $h): string
    {
        return match ($h) {
            1 => 'uma',
            2 => 'duas',
            3 => 'três',
            4 => 'quatro',
            5 => 'cinco',
            6 => 'seis',
            7 => 'sete',
            8 => 'oito',
            9 => 'nove',
            10 => 'dez',
            11 => 'onze',
            12 => 'meio-dia',
            default => (string) $h,
        };
    }

    /**
     * Conversão simples de inteiro para palavras (até milhares, PT-BR).
     * Cobertura conservadora — valores muito grandes mantêm dígitos.
     */
    private function intToWords(int $n): string
    {
        if ($n < 0) {
            return (string) $n;
        }
        if ($n === 0) {
            return 'zero';
        }
        if ($n >= 1000) {
            return (string) $n; // fallback para valores grandes
        }

        $units = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
        $teens = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
        $tens = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $hundreds = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

        if ($n === 100) {
            return 'cem';
        }

        $parts = [];
        $h = intdiv($n, 100);
        $rest = $n % 100;

        if ($h > 0) {
            $parts[] = $hundreds[$h];
        }

        if ($rest >= 10 && $rest < 20) {
            $parts[] = $teens[$rest - 10];
        } else {
            $t = intdiv($rest, 10);
            $u = $rest % 10;
            if ($t > 0) {
                $parts[] = $tens[$t];
            }
            if ($u > 0) {
                $parts[] = $units[$u];
            }
        }

        return implode(' e ', $parts);
    }
}

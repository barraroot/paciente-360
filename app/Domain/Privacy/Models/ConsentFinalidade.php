<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

/**
 * **T022 (Fase 8 — Lote A US-13.1)** — Enum tipado para `consent_records.finalidade`.
 *
 * Modelo hierárquico (Q24):
 *   - {@see self::Transacional} → implícito ao cadastro; confirma consulta, alerta receita.
 *   - {@see self::Marketing} → opt-in explícito; campanhas, lembretes proativos.
 *   - {@see self::Pesquisa} → opt-in explícito; NPS/surveys (placeholder MVP).
 */
enum ConsentFinalidade: string
{
    case Transacional = 'transacional';
    case Marketing = 'marketing';
    case Pesquisa = 'pesquisa';

    /**
     * Compartilhamento com integrações externas (webhooks / API pública).
     * Sem este consentimento o `WebhookDispatcher` (Lote D US-11.1) marca
     * o campo `paciente.id` como `<consent_withheld>` no payload.
     */
    case Integracoes = 'integracoes';

    /**
     * **T023 (Fase 18 — Q-clarify-2=B, FR-055a)** — Retenção PROLONGADA do
     * áudio bruto inbound além do prazo padrão das mídias (Fase 13). Sem
     * este consentimento, o PurgeExpiredAudioRawJob (T210) apaga o WAV/OGG
     * no prazo padrão e apenas a transcrição em texto permanece.
     *
     * STT/TTS em si (US4/US5) reusam {@see self::Transacional} como base de
     * licitude — são meios técnicos do mesmo propósito comunicacional já
     * consentido no cadastro.
     */
    case Transcricao = 'transcricao';

    public function label(): string
    {
        return match ($this) {
            self::Transacional => 'Transacional',
            self::Marketing => 'Marketing',
            self::Pesquisa => 'Pesquisa',
            self::Integracoes => 'Compartilhamento com Integrações',
            self::Transcricao => 'Armazenamento prolongado de áudio (transcrição)',
        };
    }
}

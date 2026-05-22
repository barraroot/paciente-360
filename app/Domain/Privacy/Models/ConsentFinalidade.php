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

    public function label(): string
    {
        return match ($this) {
            self::Transacional => 'Transacional',
            self::Marketing => 'Marketing',
            self::Pesquisa => 'Pesquisa',
            self::Integracoes => 'Compartilhamento com Integrações',
        };
    }
}

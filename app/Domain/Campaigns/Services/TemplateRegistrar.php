<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\CampaignTemplateMeta;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * **T146 (Fase 8 — Lote C US-9.3)** — Registra `CampaignTemplateMeta` rejeitando
 * templates sem unsubscribe (AC-9.3.3 — gate Princípio VI no momento do cadastro).
 *
 * Quando a Fase 3 sincroniza um template novo (cron de sync periódico), este
 * service é o ponto onde a metadata específica de campanha é criada — E onde
 * a validação de unsubscribe acontece. Template sem `/sair` ou equivalente
 * dispara `InvalidArgumentException` e NÃO é criado o meta — o template fica
 * VISÍVEL em messaging_channel_templates (existe na Meta) mas não usável em
 * campanhas (sem meta).
 *
 * Hook esperado: chamar `registerFromTemplate($templateId)` no fim do sync da Fase 3.
 */
final class TemplateRegistrar
{
    public function __construct(
        private readonly MetaTemplateStatusChecker $checker,
    ) {}

    /**
     * Registra/atualiza meta para um template existente em messaging_channel_templates.
     *
     * @throws InvalidArgumentException se template não tem unsubscribe (rejeita cadastro).
     */
    public function registerFromTemplate(int $messagingChannelTemplateId): CampaignTemplateMeta
    {
        $template = DB::table('messaging_channel_templates')
            ->where('id', $messagingChannelTemplateId)
            ->first();

        if ($template === null) {
            throw new InvalidArgumentException("Template #{$messagingChannelTemplateId} não encontrado em messaging_channel_templates.");
        }

        $hasUnsubscribe = $this->checker->detectUnsubscribe((string) ($template->body_preview ?? ''));

        // AC-9.3.3 — gate Princípio VI. Templates HSM que serão usados em
        // campanhas (categoria MARKETING) DEVEM ter unsubscribe; rejeita cadastro
        // do meta se categoria=MARKETING + sem unsubscribe.
        if (! $hasUnsubscribe && in_array(strtoupper((string) ($template->category ?? '')), ['MARKETING', 'UTILITY'], true) === false) {
            // Não-MARKETING → não bloqueia; meta criado com has_unsubscribe=false
            // e Gate runtime impede uso em campanhas.
        } elseif (! $hasUnsubscribe && strtoupper((string) ($template->category ?? '')) === 'MARKETING') {
            throw new InvalidArgumentException(
                "Template MARKETING #{$messagingChannelTemplateId} sem comando de unsubscribe (/sair, descadastrar, etc.) ".
                'NÃO pode ser registrado para uso em campanhas (AC-9.3.3 — Princípio VI).',
            );
        }

        return CampaignTemplateMeta::query()->updateOrCreate(
            ['messaging_channel_template_id' => $messagingChannelTemplateId],
            [
                'tenant_id' => $template->tenant_id,
                'has_unsubscribe' => $hasUnsubscribe,
                'last_compliance_check_at' => now(),
                'last_known_meta_status' => $template->meta_template_status ?? 'pending',
            ],
        );
    }
}

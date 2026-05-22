<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\CampaignTemplateMeta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * **T145 (Fase 8 — Lote C US-9.3)** — Consulta status do template Meta com cache.
 *
 * Estratégia (Q AC-9.3.5):
 *   1. Cache local em `campaign_templates_meta.last_known_meta_status` com TTL
 *      de `config('finalization.campaign_template_meta_status_cache_minutes', 30)`.
 *   2. Cache hit (`isCheckFresh()` true): retorna o snapshot armazenado.
 *   3. Cache miss/stale: consulta Meta Graph API via `messaging` service da
 *      Fase 3 (stub no MVP — apenas re-lê `messaging_channel_templates.meta_template_status`
 *      que é sincronizado pelo cron de Fase 3).
 *   4. Atualiza `last_compliance_check_at` + `last_known_meta_status`.
 *
 * **DEFERRED em MVP**: chamada HTTP real à Graph API para refresh on-demand
 * fica para Lote C futuro. Por enquanto, lê do mirror local da Fase 3 que
 * já tem mecanismo de sync periódico.
 */
final class MetaTemplateStatusChecker
{
    /**
     * Retorna o status atual do template, atualizando cache se necessário.
     */
    public function checkStatus(CampaignTemplateMeta $meta): string
    {
        $ttlMinutes = (int) config('finalization.campaign_template_meta_status_cache_minutes', 30);

        if ($meta->isCheckFresh($ttlMinutes) && $meta->last_known_meta_status !== null) {
            return $meta->last_known_meta_status;
        }

        $remoteStatus = $this->fetchRemoteStatus($meta->messaging_channel_template_id);

        $meta->update([
            'last_compliance_check_at' => Carbon::now(),
            'last_known_meta_status' => $remoteStatus,
        ]);

        return $remoteStatus;
    }

    /**
     * Retorna a row de meta para um template_id, criando o registro slim se
     * ainda não existir (cobre o caso de template sincronizado pela Fase 3
     * sem registro de meta ainda).
     */
    public function getOrCreateMeta(int $messagingChannelTemplateId): CampaignTemplateMeta
    {
        $existing = CampaignTemplateMeta::query()
            ->where('messaging_channel_template_id', $messagingChannelTemplateId)
            ->first();

        if ($existing instanceof CampaignTemplateMeta) {
            return $existing;
        }

        $template = DB::table('messaging_channel_templates')
            ->where('id', $messagingChannelTemplateId)
            ->first();

        if ($template === null) {
            throw new \RuntimeException("Template #{$messagingChannelTemplateId} não encontrado em messaging_channel_templates.");
        }

        return CampaignTemplateMeta::query()->create([
            'tenant_id' => $template->tenant_id,
            'messaging_channel_template_id' => $messagingChannelTemplateId,
            // AC-9.3.3 — detect unsubscribe no body_preview no momento da criação do meta.
            'has_unsubscribe' => $this->detectUnsubscribe($template->body_preview ?? ''),
            'last_compliance_check_at' => Carbon::now(),
            'last_known_meta_status' => $template->meta_template_status ?? 'pending',
        ]);
    }

    /**
     * Detecta presença de comando/link de unsubscribe no corpo do template.
     *
     * Heurística MVP — qualquer ocorrência de `/sair`, `/STOP`, "descadastrar"
     * ou "unsubscribe" (case-insensitive) é aceita. Pode ser refinado para
     * regex mais estrita quando templates HSM forem cadastrados ativamente.
     */
    public function detectUnsubscribe(string $bodyPreview): bool
    {
        if ($bodyPreview === '') {
            return false;
        }

        $needles = ['/sair', '/stop', 'descadastrar', 'unsubscribe', 'parar de receber'];
        $lower = mb_strtolower($bodyPreview);

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consulta o status remoto. Em MVP, lê o mirror local sincronizado pelo
     * cron da Fase 3. Refresh real via Graph API fica para slice futuro.
     */
    private function fetchRemoteStatus(int $messagingChannelTemplateId): string
    {
        $template = DB::table('messaging_channel_templates')
            ->where('id', $messagingChannelTemplateId)
            ->first();

        if ($template === null) {
            Log::warning('campaigns.template.fetch_remote_status_not_found', [
                'messaging_channel_template_id' => $messagingChannelTemplateId,
            ]);

            return 'rejected'; // fail-closed: status indefinido vira rejected
        }

        return (string) ($template->meta_template_status ?? 'pending');
    }
}

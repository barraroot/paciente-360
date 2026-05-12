<?php

namespace App\Domain\Messaging\Channel\Services;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use Twilio\Rest\Client;

/**
 * T071a — Serviço de sincronização de templates via Twilio Content API.
 *
 * Usa updateOrCreate para idempotência — múltiplas execuções não
 * criam duplicatas, apenas atualizam o status.
 */
final class ChannelTemplateSyncService
{
    public function __construct(
        private readonly ?Client $clientOverride = null,
    ) {}

    /**
     * Sincroniza templates do canal com a Twilio Content API.
     *
     * @return int Número de templates sincronizados
     */
    public function syncFor(Channel $channel): int
    {
        $client = $this->resolveClient($channel);

        $templates = $client->content->v1->contents->read();

        $count = 0;

        foreach ($templates as $tpl) {
            $bodyPreview = $tpl->types['twilio/text']['body'] ?? null;

            ChannelTemplate::updateOrCreate(
                [
                    'channel_id' => $channel->id,
                    'provider_template_id' => $tpl->sid,
                ],
                [
                    'tenant_id' => $channel->tenant_id,
                    'meta_template_name' => $tpl->friendlyName,
                    'meta_template_status' => $tpl->status,
                    'language' => $tpl->language,
                    'body_preview' => $bodyPreview,
                    'last_synced_at' => now(),
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Resolve o Twilio Client — usa override (testes) ou cria com creds do channel.
     */
    private function resolveClient(Channel $channel): Client
    {
        if ($this->clientOverride !== null) {
            return $this->clientOverride;
        }

        $credentials = is_array($channel->credentials_encrypted)
            ? $channel->credentials_encrypted
            : [];

        return new Client(
            (string) ($credentials['account_sid'] ?? ''),
            (string) ($credentials['auth_token'] ?? ''),
        );
    }
}

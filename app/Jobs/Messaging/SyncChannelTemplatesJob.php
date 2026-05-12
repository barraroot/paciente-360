<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Services\ChannelTemplateSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * T071b — Job de sincronização de templates de canal via Twilio Content API.
 *
 * Não estende TenantAwareJob porque pode ser dispachado fora do contexto
 * HTTP via webhook ou CLI. Tenant context resolvido via channel.tenant_id.
 *
 * Retries com backoff exponencial (3 tentativas).
 */
class SyncChannelTemplatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Número máximo de tentativas.
     */
    public int $tries = 3;

    /**
     * Backoff em segundos entre tentativas.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly Channel $channel,
    ) {}

    /**
     * Executa a sincronização de templates.
     *
     * Resolve o Twilio Client do container para permitir mock em testes.
     * Se o container não tiver um Client bound, passa null para o service
     * criar um com as credenciais do canal.
     */
    public function handle(): void
    {
        $clientOverride = app()->bound(Client::class)
            ? app(Client::class)
            : null;

        $service = new ChannelTemplateSyncService($clientOverride);

        $count = $service->syncFor($this->channel);

        Log::info('channel_templates.synced', [
            'channel_id' => $this->channel->id,
            'tenant_id' => $this->channel->tenant_id,
            'count' => $count,
        ]);
    }
}

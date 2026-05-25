<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channel\Services;

use App\Domain\Messaging\Channel\Adapters\EvolutionApiAdapter;
use App\Domain\Messaging\Channel\Adapters\QrPayload;
use App\Domain\Messaging\Channel\Models\Channel;
use Illuminate\Support\Facades\Crypt;

/**
 * Feature 014 — Orquestra o ciclo de vida da instância Evolution de um canal.
 *
 * Cria a instância no servidor, persiste `instance_name` e `instance_token`
 * (cifrado) em `provider_metadata`, reconcilia o estado de conexão e encerra a
 * sessão. O segredo nunca é exposto por `ChannelResource`/logs (Princípio I).
 *
 * @see specs/014-channel-provider-integration/research.md §R2, §R3
 */
final class EvolutionInstanceService
{
    public function __construct(
        private readonly EvolutionApiAdapter $adapter,
    ) {}

    /**
     * Cria a instância no servidor Evolution para um canal já persistido e
     * devolve o QR Code para pareamento. Define o status como `conectando`.
     */
    public function createForChannel(Channel $channel): ?QrPayload
    {
        $connection = $this->adapter->createInstance($channel);

        $this->mergeMetadata($channel, [
            'instance_name' => $connection->instanceName,
            'instance_token' => $connection->instanceToken !== null
                ? Crypt::encryptString($connection->instanceToken)
                : null,
        ]);

        $channel->update(['status' => 'conectando']);

        return $connection->qr;
    }

    /**
     * Gera/regenera o QR Code de pareamento.
     */
    public function regenerateQr(Channel $channel): QrPayload
    {
        return $this->adapter->getQrCode($channel);
    }

    /**
     * Consulta o estado real no provedor e reconcilia o `status` do canal.
     * Retorna o status interno resultante.
     */
    public function refreshState(Channel $channel): string
    {
        $state = $this->adapter->connectionState($channel);
        $status = Channel::mapEvolutionState($state);

        if ($channel->status !== $status) {
            $channel->update(['status' => $status]);
        }

        return $status;
    }

    /**
     * Encerra a sessão/instância no provedor.
     */
    public function terminate(Channel $channel): void
    {
        $this->adapter->disconnect($channel);
    }

    /**
     * @param array<string, mixed> $patch
     */
    private function mergeMetadata(Channel $channel, array $patch): void
    {
        $current = is_array($channel->provider_metadata) ? $channel->provider_metadata : [];

        $channel->update(['provider_metadata' => array_merge($current, $patch)]);
    }
}

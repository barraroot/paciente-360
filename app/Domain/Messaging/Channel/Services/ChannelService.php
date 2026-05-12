<?php

namespace App\Domain\Messaging\Channel\Services;

use App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter;
use App\Domain\Messaging\Channel\Events\CanalConectado;
use App\Domain\Messaging\Channel\Events\CanalDesconectado;
use App\Domain\Messaging\Channel\Exceptions\ChannelAlreadyConnectedException;
use App\Domain\Messaging\Channel\Exceptions\ChannelHasActiveConversationsException;
use App\Domain\Messaging\Channel\Exceptions\InvalidCredentialsException;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Crypt;

/**
 * T069 — Serviço de domínio para conexão e desconexão de canais.
 *
 * Orquestra: validação de credenciais, dedup, persistência e auditoria.
 * Não chama API da Meta/Twilio diretamente no ciclo HTTP — delegado ao adapter.
 */
final class ChannelService
{
    public function __construct(
        private readonly WhatsAppCloudAdapter $whatsAppAdapter,
    ) {}

    /**
     * Conecta um novo canal ao tenant.
     *
     * @param array<string, mixed> $credentials
     *
     * @throws InvalidCredentialsException se as credenciais forem rejeitadas pelo provider
     * @throws ChannelAlreadyConnectedException se o canal já estiver conectado
     */
    public function connect(
        int $tenantId,
        string $type,
        string $name,
        array $credentials,
        ?int $executorId = null,
    ): Channel {
        $adapter = $this->resolveAdapter($type);

        if (! $adapter->validateCredentials($credentials)) {
            throw new InvalidCredentialsException;
        }

        if ($type === 'whatsapp') {
            $existingChannel = Channel::where('tenant_id', $tenantId)
                ->where('type', 'whatsapp')
                ->whereJsonContains('provider_metadata->messaging_service_sid', $credentials['messaging_service_sid'] ?? '')
                ->first();

            if ($existingChannel !== null) {
                throw new ChannelAlreadyConnectedException;
            }
        }

        $providerMetadata = $this->buildProviderMetadata($type, $credentials);

        // O cast 'encrypted' do Channel espera uma string já encriptada
        // (o model não usa 'encrypted:array'). Serializa manualmente para
        // corresponder ao padrão das factories: encrypt([...]) antes de fill.
        $encryptedCredentials = Crypt::encrypt($credentials);

        /** @var Channel $channel */
        $channel = Channel::create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'name' => $name,
            'status' => 'ativo',
            'credentials_encrypted' => $encryptedCredentials,
            'provider_metadata' => $providerMetadata,
        ]);

        event(new CanalConectado($channel, $executorId));

        return $channel;
    }

    /**
     * Desconecta (soft delete) um canal do tenant.
     *
     * @throws ChannelHasActiveConversationsException se o canal tiver conversas ativas e force=false
     */
    public function disconnect(Channel $channel, ?int $executorId = null, bool $force = false): void
    {
        if (! $force) {
            $hasActiveConversations = $channel->conversations()
                ->whereIn('status', ['aberta', 'pendente', 'reaberta'])
                ->exists();

            if ($hasActiveConversations) {
                throw new ChannelHasActiveConversationsException;
            }
        }

        $channel->update(['status' => 'desconectado']);
        $channel->delete();

        // Grava auditoria diretamente para garantir persistência mesmo quando
        // o evento é interceptado por Event::fake() em testes.
        AuditLog::create([
            'tenant_id' => $channel->tenant_id,
            'user_id' => $executorId,
            'executor_id' => $executorId,
            'actor_type' => $executorId !== null ? 'user' : 'system',
            'action' => 'channel.disconnected',
            'auditable_type' => Channel::class,
            'auditable_id' => $channel->id,
            'payload' => ['canal_id' => $channel->id],
        ]);

        event(new CanalDesconectado($channel, null, $executorId));
    }

    /**
     * Resolve o adapter correto para o tipo de canal.
     */
    private function resolveAdapter(string $type): WhatsAppCloudAdapter
    {
        return match ($type) {
            'whatsapp' => $this->whatsAppAdapter,
            default => $this->whatsAppAdapter,
        };
    }

    /**
     * Constrói os metadados não-secretos do provider.
     *
     * @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    private function buildProviderMetadata(string $type, array $credentials): array
    {
        if ($type === 'whatsapp') {
            return [
                'messaging_service_sid' => $credentials['messaging_service_sid'] ?? null,
                'whatsapp_sender' => $credentials['whatsapp_sender'] ?? null,
                'account_sid' => $credentials['account_sid'] ?? null,
            ];
        }

        return [];
    }
}

<?php

namespace App\Domain\Messaging\Channel\Services;

use App\Domain\Messaging\Channel\Adapters\ChannelAdapter;
use App\Domain\Messaging\Channel\Adapters\InstagramGraphAdapter;
use App\Domain\Messaging\Channel\Adapters\WebWidgetAdapter;
use App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter;
use App\Domain\Messaging\Channel\Enums\ChannelProvider;
use App\Domain\Messaging\Channel\Events\CanalConectado;
use App\Domain\Messaging\Channel\Events\CanalDesconectado;
use App\Domain\Messaging\Channel\Exceptions\ChannelAlreadyConnectedException;
use App\Domain\Messaging\Channel\Exceptions\ChannelHasActiveConversationsException;
use App\Domain\Messaging\Channel\Exceptions\InvalidCredentialsException;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Services\WidgetAuthService;
use App\Models\AuditLog;
use App\Support\Metrics\MessagingMetricsContract;
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
        private readonly InstagramGraphAdapter $instagramAdapter,
        private readonly WebWidgetAdapter $webWidgetAdapter,
        private readonly WidgetAuthService $widgetAuthService,
        private readonly EvolutionInstanceService $evolutionInstances,
        private readonly MessagingMetricsContract $metrics,
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
        string $provider = 'twilio',
    ): Channel {
        // Feature 014 — um WhatsApp ativo/conectando por tenant (R7).
        if ($type === 'whatsapp') {
            $this->assertNoActiveWhatsapp($tenantId);
        }

        // Provedor não oficial (Evolution): sem credenciais por tenant — a
        // "validação" é o pareamento por QR (feito pelo EvolutionInstanceService).
        $isEvolution = $type === 'whatsapp' && $provider === 'evolution';

        if (! $isEvolution) {
            $adapter = $this->resolveAdapter($type);

            if (! $adapter->validateCredentials($credentials)) {
                throw new InvalidCredentialsException;
            }
        }

        if ($type === 'whatsapp' && ! $isEvolution) {
            $existingChannel = Channel::where('tenant_id', $tenantId)
                ->where('type', 'whatsapp')
                ->whereJsonContains('provider_metadata->messaging_service_sid', $credentials['messaging_service_sid'] ?? '')
                ->first();

            if ($existingChannel !== null) {
                throw new ChannelAlreadyConnectedException;
            }
        }

        if ($type === 'instagram') {
            $existingChannel = Channel::where('tenant_id', $tenantId)
                ->where('type', 'instagram')
                ->whereJsonContains('provider_metadata->ig_business_account_id', $credentials['ig_business_account_id'] ?? '')
                ->first();

            if ($existingChannel !== null) {
                throw new ChannelAlreadyConnectedException;
            }
        }

        $providerMetadata = $isEvolution ? [] : $this->buildProviderMetadata($type, $credentials);

        // O cast 'encrypted' do Channel espera uma string já encriptada
        // (o model não usa 'encrypted:array'). Serializa manualmente para
        // corresponder ao padrão das factories: encrypt([...]) antes de fill.
        $encryptedCredentials = (! $isEvolution && $type !== 'web') ? Crypt::encrypt($credentials) : null;

        // Evolution inicia em 'conectando' (aguardando pareamento por QR);
        // demais provedores ativam após validação de credenciais.
        $status = $isEvolution ? 'conectando' : 'ativo';

        /** @var Channel $channel */
        $channel = Channel::create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'provider' => $provider,
            'name' => $name,
            'status' => $status,
            'credentials_encrypted' => $encryptedCredentials,
            'provider_metadata' => $providerMetadata,
        ]);

        // Web channel: auto-create WebWidgetConfig with generated public_key
        if ($type === 'web') {
            $publicKey = $this->widgetAuthService->generatePublicKey();

            WebWidgetConfig::create([
                'tenant_id' => $tenantId,
                'channel_id' => $channel->id,
                'public_key' => $publicKey,
                'allowed_origins' => [],
                'appearance' => [
                    'primary_color' => '#2563eb',
                    'position' => 'bottom-right',
                    'button_label' => 'Fale conosco',
                ],
                'initial_message' => 'Olá! Como podemos ajudar?',
                'business_hours' => [
                    'monday' => '08:00-18:00',
                    'tuesday' => '08:00-18:00',
                    'wednesday' => '08:00-18:00',
                    'thursday' => '08:00-18:00',
                    'friday' => '08:00-18:00',
                    'saturday' => null,
                    'sunday' => null,
                    'timezone' => 'America/Sao_Paulo',
                ],
                'outside_hours_behavior' => 'fila',
                'pre_chat_form' => 'opcional',
            ]);

            // Store public_key in channel provider_metadata for fast lookup
            $channel->update([
                'provider_metadata' => array_merge(
                    is_array($channel->provider_metadata) ? $channel->provider_metadata : [],
                    ['public_key' => $publicKey],
                ),
            ]);
        }

        event(new CanalConectado($channel, $executorId));
        $this->metrics->channelConnected($tenantId, $provider, $status);

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

        // Feature 014 — encerra a sessão/instância no servidor Evolution antes do soft-delete.
        $providerValue = $channel->provider instanceof ChannelProvider
            ? $channel->provider->value
            : (string) $channel->provider;

        if ($channel->type === 'whatsapp' && $providerValue === 'evolution') {
            $this->evolutionInstances->terminate($channel);
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
        $this->metrics->channelDisconnected($channel->tenant_id, $providerValue, $force ? 'forced' : 'manual');
    }

    /**
     * Feature 014 — garante um único canal WhatsApp ativo/conectando por tenant.
     * Trocar de provedor exige desconectar o atual antes (R7). O índice UNIQUE
     * parcial no banco é o gate final; esta checagem dá erro amigável.
     *
     * @throws ChannelAlreadyConnectedException
     */
    private function assertNoActiveWhatsapp(int $tenantId): void
    {
        $exists = Channel::where('tenant_id', $tenantId)
            ->where('type', 'whatsapp')
            ->whereIn('status', ['ativo', 'conectando'])
            ->exists();

        if ($exists) {
            throw new ChannelAlreadyConnectedException;
        }
    }

    /**
     * Resolve o adapter correto para o tipo de canal.
     */
    private function resolveAdapter(string $type): ChannelAdapter
    {
        return match ($type) {
            'whatsapp' => $this->whatsAppAdapter,
            'instagram' => $this->instagramAdapter,
            'web' => $this->webWidgetAdapter,
            default => $this->whatsAppAdapter,
        };
    }

    /**
     * Constrói os metadados não-secretos do provider.
     *
     * Para Instagram: page_id e ig_business_account_id são públicos; ig_username
     * é buscado na Graph API. O page_access_token vai em credentials_encrypted.
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

        if ($type === 'instagram') {
            $igUsername = $this->instagramAdapter->fetchIgUsername($credentials);

            return [
                'page_id' => $credentials['page_id'] ?? null,
                'ig_business_account_id' => $credentials['ig_business_account_id'] ?? null,
                'ig_username' => $igUsername,
            ];
        }

        return [];
    }
}

<?php

namespace App\Domain\Messaging\Channel\Adapters;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerService;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitOpenException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * T068 — Adapter Twilio WhatsApp implementando ChannelAdapter.
 *
 * Cada canal tem suas próprias credenciais (account_sid, auth_token).
 * O Client é criado dinamicamente por channel — evita state global.
 *
 * `$clientOverride` permite injeção de mock em testes sem precisar
 * de credenciais reais.
 *
 * @see ChannelAdapter
 * @see specs/003-omnichannel-inbox/research.md — R2
 */
final class WhatsAppCloudAdapter implements ChannelAdapter
{
    public function __construct(
        private readonly CircuitBreakerService $circuitBreaker,
        private readonly ?Client $clientOverride = null,
    ) {}

    public function getType(): string
    {
        return 'whatsapp';
    }

    /**
     * Envia mensagem pelo Twilio WhatsApp.
     *
     * @throws CircuitOpenException
     */
    public function send(Channel $channel, OutboundMessage $message): MessageDispatchResult
    {
        $client = $this->clientFor($channel);
        $providerMetadata = is_array($channel->provider_metadata) ? $channel->provider_metadata : [];
        $sender = $providerMetadata['whatsapp_sender'] ?? '';
        $messagingServiceSid = $providerMetadata['messaging_service_sid'] ?? null;

        $to = 'whatsapp:'.$message->conversationExternalThreadId;

        try {
            $response = $this->circuitBreaker->for('twilio')->call(function () use (
                $client, $to, $sender, $messagingServiceSid, $message
            ) {
                if ($message->contentType === 'template') {
                    $params = [
                        'from' => $sender,
                        'contentSid' => $message->templateProviderId,
                        'contentVariables' => json_encode($message->templateVariables, JSON_THROW_ON_ERROR),
                    ];

                    if ($messagingServiceSid !== null) {
                        $params['messagingServiceSid'] = $messagingServiceSid;
                    }

                    return $client->messages->create($to, $params);
                }

                if ($message->contentType === 'text') {
                    $params = [
                        'from' => $sender,
                        'body' => $message->body ?? '',
                    ];

                    if ($messagingServiceSid !== null) {
                        $params['messagingServiceSid'] = $messagingServiceSid;
                    }

                    return $client->messages->create($to, $params);
                }

                // Unsupported content type for outbound
                return null;
            });

            if ($response === null) {
                return new MessageDispatchResult(
                    accepted: false,
                    failureReason: "Unsupported content type for outbound: {$message->contentType}",
                );
            }

            return new MessageDispatchResult(
                accepted: true,
                externalId: $response->sid,
            );
        } catch (TwilioException $e) {
            return new MessageDispatchResult(
                accepted: false,
                failureReason: $e->getMessage(),
            );
        }
    }

    /**
     * Valida credenciais tentando uma chamada de lista de mensagens.
     *
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(array $credentials): bool
    {
        try {
            if ($this->clientOverride !== null) {
                $client = $this->clientOverride;
            } elseif (app()->bound(Client::class)) {
                $client = app(Client::class);
            } else {
                $client = new Client(
                    (string) ($credentials['account_sid'] ?? ''),
                    (string) ($credentials['auth_token'] ?? ''),
                );
            }

            $client->messages->read(['limit' => 1]);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Parseia o payload Twilio de webhook inbound para DTO normalizado.
     *
     * @param array<string, mixed> $payload
     */
    public function parseInboundWebhook(array $payload): InboundMessageDto
    {
        $from = (string) ($payload['From'] ?? '');
        // Remove o prefixo "whatsapp:" do número
        $externalThreadId = str_starts_with($from, 'whatsapp:')
            ? substr($from, strlen('whatsapp:'))
            : $from;

        $numMedia = (int) ($payload['NumMedia'] ?? 0);
        $contentType = 'text';
        $mediaUrls = [];

        if ($numMedia > 0) {
            $mediaContentType = (string) ($payload['MediaContentType0'] ?? '');

            $contentType = match (true) {
                str_starts_with($mediaContentType, 'image/') => 'image',
                str_starts_with($mediaContentType, 'audio/') => 'audio',
                str_starts_with($mediaContentType, 'video/') => 'video',
                default => 'document',
            };

            for ($i = 0; $i < $numMedia; $i++) {
                $url = $payload["MediaUrl{$i}"] ?? null;
                if ($url !== null) {
                    $mediaUrls[] = (string) $url;
                }
            }
        }

        return new InboundMessageDto(
            providerType: 'twilio',
            externalThreadId: $externalThreadId,
            externalMessageId: (string) ($payload['MessageSid'] ?? ''),
            contentType: $contentType,
            body: isset($payload['Body']) && $payload['Body'] !== '' ? (string) $payload['Body'] : null,
            mediaUrls: $mediaUrls,
            rawProviderMetadata: $payload,
        );
    }

    /**
     * Retorna o Client Twilio para o channel.
     *
     * Ordem de precedência:
     *  1. `$clientOverride` injetado no construtor (testes unitários diretos).
     *  2. Binding de `Client::class` no container (ex.: `$this->app->instance(Client::class, $mock)` nos testes).
     *  3. Novo `Client` com credenciais do canal (produção).
     */
    private function clientFor(Channel $channel): Client
    {
        if ($this->clientOverride !== null) {
            return $this->clientOverride;
        }

        // Permite injeção de mock via container sem precisar de credenciais reais
        if (app()->bound(Client::class)) {
            return app(Client::class);
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

<?php

namespace App\Domain\Messaging\Channel\Adapters;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerService;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitOpenException;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * T189 — Adapter Meta Graph API para Instagram Direct (US-4.2).
 *
 * Implementa ChannelAdapter usando Guzzle diretamente (Twilio não suporta IG DM
 * — research R3). Versão da Graph API lida de config, nunca hardcoded (R7).
 *
 * Princípio I — page_access_token guardado criptografado em credentials_encrypted;
 *               nunca aparece em provider_metadata nem logs.
 * Princípio VI — circuit breaker envolve toda chamada HTTP outbound à Meta.
 *
 * @see specs/003-omnichannel-inbox/research.md — R3, R7
 */
class InstagramGraphAdapter implements ChannelAdapter
{
    private const GRAPH_BASE = 'https://graph.facebook.com';

    public function __construct(
        private readonly CircuitBreakerService $circuitBreaker,
        private readonly ?GuzzleClient $clientOverride = null,
    ) {}

    public function getType(): string
    {
        return 'instagram';
    }

    /**
     * Valida credenciais consultando dois endpoints da Graph API:
     *  1. GET /me?access_token — verifica que o token é válido (200 OK).
     *  2. GET /{ig_business_account_id}?fields=username,account_type — verifica BUSINESS.
     *
     * Retorna false em qualquer erro HTTP ou quando account_type != BUSINESS.
     *
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(array $credentials): bool
    {
        $token = (string) ($credentials['page_access_token'] ?? '');
        $igBusinessAccountId = (string) ($credentials['ig_business_account_id'] ?? '');

        if ($token === '' || $igBusinessAccountId === '') {
            return false;
        }

        $version = config('messaging.providers.meta.graph_api_version', 'v21.0');
        $client = $this->guzzleClient();

        try {
            // Passo 1 — Valida o token chamando /me
            $meResponse = $client->get(
                self::GRAPH_BASE."/{$version}/me",
                ['query' => ['access_token' => $token]]
            );

            if ($meResponse->getStatusCode() !== 200) {
                return false;
            }

            // Passo 2 — Verifica tipo de conta (precisa ser BUSINESS ou CREATOR)
            $accountResponse = $client->get(
                self::GRAPH_BASE."/{$version}/{$igBusinessAccountId}",
                ['query' => [
                    'fields' => 'username,account_type',
                    'access_token' => $token,
                ]]
            );

            if ($accountResponse->getStatusCode() !== 200) {
                return false;
            }

            /** @var array<string, mixed> $accountData */
            $accountData = json_decode((string) $accountResponse->getBody(), true) ?? [];
            $accountType = (string) ($accountData['account_type'] ?? '');

            // Aceita BUSINESS e CREATOR (ambos suportam Messaging API)
            return in_array($accountType, ['BUSINESS', 'CREATOR'], true);
        } catch (BadResponseException $e) {
            Log::warning('InstagramGraphAdapter: validateCredentials recebeu erro HTTP', [
                'status' => $e->getResponse()->getStatusCode(),
                'ig_business_account_id' => $igBusinessAccountId,
            ]);

            return false;
        } catch (GuzzleException $e) {
            Log::warning('InstagramGraphAdapter: validateCredentials falhou', [
                'message' => $e->getMessage(),
                'ig_business_account_id' => $igBusinessAccountId,
            ]);

            return false;
        }
    }

    /**
     * Envia mensagem de texto ao IGSID do destinatário via Graph API.
     *
     * POST /{version}/{ig_business_account_id}/messages
     * Authorization: Bearer {page_access_token}
     *
     * Envolto no circuit breaker para provider 'meta'.
     *
     * @throws CircuitOpenException
     */
    public function send(Channel $channel, OutboundMessage $message): MessageDispatchResult
    {
        $credentials = $this->decryptCredentials($channel);
        $token = (string) ($credentials['page_access_token'] ?? '');
        $providerMetadata = is_array($channel->provider_metadata) ? $channel->provider_metadata : [];
        $igBusinessAccountId = (string) ($providerMetadata['ig_business_account_id'] ?? '');

        if ($token === '' || $igBusinessAccountId === '') {
            return new MessageDispatchResult(
                accepted: false,
                failureReason: 'Credenciais incompletas: page_access_token ou ig_business_account_id ausente',
            );
        }

        $version = config('messaging.providers.meta.graph_api_version', 'v21.0');
        $client = $this->guzzleClient();

        try {
            $responseData = $this->circuitBreaker->for('meta')->call(function () use (
                $client, $version, $igBusinessAccountId, $token, $message
            ) {
                $body = [
                    'recipient' => ['id' => $message->conversationExternalThreadId],
                    'message' => ['text' => $message->body ?? ''],
                ];

                $response = $client->post(
                    self::GRAPH_BASE."/{$version}/{$igBusinessAccountId}/messages",
                    [
                        'headers' => [
                            'Authorization' => "Bearer {$token}",
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $body,
                    ]
                );

                if ($response->getStatusCode() >= 400) {
                    throw new \RuntimeException(
                        'Graph API erro: '.$response->getStatusCode().' '.$response->getBody()
                    );
                }

                return json_decode((string) $response->getBody(), true) ?? [];
            });

            $messageId = (string) ($responseData['message_id'] ?? '');

            return new MessageDispatchResult(
                accepted: true,
                externalId: $messageId !== '' ? $messageId : null,
                providerMetadata: ['raw' => $responseData],
            );
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = (string) $e->getResponse()->getBody();

            Log::error('InstagramGraphAdapter: send falhou', [
                'status' => $statusCode,
                'ig_business_account_id' => $igBusinessAccountId,
                'to_igsid' => $message->conversationExternalThreadId,
            ]);

            return new MessageDispatchResult(
                accepted: false,
                failureReason: "Graph API {$statusCode}: {$body}",
            );
        } catch (\Throwable $e) {
            Log::error('InstagramGraphAdapter: send exception', [
                'message' => $e->getMessage(),
                'ig_business_account_id' => $igBusinessAccountId,
            ]);

            return new MessageDispatchResult(
                accepted: false,
                failureReason: $e->getMessage(),
            );
        }
    }

    /**
     * Parseia o payload bruto de webhook Instagram para o DTO normalizado.
     *
     * Estrutura Meta Instagram:
     * ```json
     * {
     *   "object": "instagram",
     *   "entry": [
     *     {
     *       "id": "<ig_business_account_id>",
     *       "time": 1234567890,
     *       "messaging": [
     *         {
     *           "sender": {"id": "<IGSID>"},
     *           "recipient": {"id": "<ig_business_account_id>"},
     *           "timestamp": 1234567890123,
     *           "message": {
     *             "mid": "<message_id>",
     *             "text": "...",
     *             "attachments": [{"type": "image", "payload": {"url": "..."}}]
     *           }
     *         }
     *       ]
     *     }
     *   ]
     * }
     * ```
     *
     * @param array<string, mixed> $payload O evento individual de messaging (entry.messaging[0])
     *                                      OU o payload completo — suporta ambos.
     */
    public function parseInboundWebhook(array $payload): InboundMessageDto
    {
        // Suporta payload completo (entry[]) ou evento individual (sender/message direto)
        $event = $payload;

        if (isset($payload['entry'][0]['messaging'][0])) {
            $event = $payload['entry'][0]['messaging'][0];
        }

        $senderIgsid = (string) ($event['sender']['id'] ?? '');
        $messageData = $event['message'] ?? [];
        $mid = (string) ($messageData['mid'] ?? '');
        $text = isset($messageData['text']) && $messageData['text'] !== '' ? (string) $messageData['text'] : null;

        // Timestamp em milissegundos → Carbon
        $timestampMs = isset($event['timestamp']) ? (int) $event['timestamp'] : null;
        $providerTimestamp = $timestampMs !== null
            ? Carbon::createFromTimestampMs($timestampMs)
            : null;

        // Attachments: image | audio | video | file → content_type + mediaUrls
        $attachments = (array) ($messageData['attachments'] ?? []);
        $contentType = 'text';
        $mediaUrls = [];

        if (count($attachments) > 0) {
            $firstAttachment = $attachments[0];
            $attachmentType = (string) ($firstAttachment['type'] ?? 'file');

            $contentType = match ($attachmentType) {
                'image' => 'image',
                'audio' => 'audio',
                'video' => 'video',
                default => 'document',
            };

            foreach ($attachments as $attachment) {
                $url = $attachment['payload']['url'] ?? null;
                if ($url !== null) {
                    $mediaUrls[] = (string) $url;
                }
            }
        }

        return new InboundMessageDto(
            providerType: 'meta',
            externalThreadId: $senderIgsid,
            externalMessageId: $mid,
            contentType: $contentType,
            body: $text,
            mediaUrls: $mediaUrls,
            rawProviderMetadata: $event,
            providerTimestamp: $providerTimestamp,
        );
    }

    /**
     * Retorna o username da conta Instagram a partir do ig_business_account_id.
     * Usado pelo ChannelService para preencher provider_metadata.ig_username.
     *
     * @param array<string, mixed> $credentials
     */
    public function fetchIgUsername(array $credentials): ?string
    {
        $token = (string) ($credentials['page_access_token'] ?? '');
        $igBusinessAccountId = (string) ($credentials['ig_business_account_id'] ?? '');

        if ($token === '' || $igBusinessAccountId === '') {
            return null;
        }

        $version = config('messaging.providers.meta.graph_api_version', 'v21.0');
        $client = $this->guzzleClient();

        try {
            $response = $client->get(
                self::GRAPH_BASE."/{$version}/{$igBusinessAccountId}",
                ['query' => [
                    'fields' => 'username,account_type',
                    'access_token' => $token,
                ]]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            /** @var array<string, mixed> $data */
            $data = json_decode((string) $response->getBody(), true) ?? [];

            return isset($data['username']) ? (string) $data['username'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Decripta as credenciais do canal.
     *
     * @return array<string, mixed>
     */
    private function decryptCredentials(Channel $channel): array
    {
        $raw = $channel->credentials_encrypted;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            try {
                $decoded = Crypt::decrypt($raw);

                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    /**
     * Retorna o Guzzle Client: override injetado (testes) ou instância padrão.
     */
    private function guzzleClient(): GuzzleClient
    {
        return $this->clientOverride ?? new GuzzleClient([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
    }
}

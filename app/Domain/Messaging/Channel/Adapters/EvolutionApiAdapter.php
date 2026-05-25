<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channel\Adapters;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Feature 014 — Adapter da Evolution API v2 (WhatsApp NÃO oficial via Baileys).
 *
 * Servidor auto-hospedado (config `messaging.providers.evolution`, nunca input
 * do tenant). Cada canal de tenant é uma instância. Implementa o transporte
 * (`ChannelAdapter`) e o ciclo de vida por QR (`SupportsQrConnection`).
 *
 * HTTP via Laravel Http client (fakeável em testes com `Http::fake()`).
 *
 * @see specs/014-channel-provider-integration/research.md §R2, §R3
 */
final class EvolutionApiAdapter implements ChannelAdapter, SupportsQrConnection
{
    public function __construct(
        private readonly CircuitBreakerService $circuitBreaker,
    ) {}

    public function getType(): string
    {
        return 'whatsapp';
    }

    // -------------------------------------------------------------------------
    // SupportsQrConnection — ciclo de vida
    // -------------------------------------------------------------------------

    public function createInstance(Channel $channel): InstanceConnection
    {
        $instanceName = $this->instanceName($channel);

        $response = $this->http()->post('/instance/create', [
            'instanceName' => $instanceName,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
            'webhook' => [
                'url' => $this->webhookUrl($instanceName),
                'byEvents' => false,
                'headers' => ['apikey' => $this->webhookSecret()],
                'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'],
            ],
        ])->throw()->json();

        $qr = $response['qrcode'] ?? null;

        return new InstanceConnection(
            instanceName: $response['instance']['instanceName'] ?? $instanceName,
            instanceToken: $response['hash'] ?? ($response['instance']['token'] ?? null),
            qr: $qr !== null ? $this->toQrPayload($qr) : null,
        );
    }

    public function getQrCode(Channel $channel): QrPayload
    {
        $response = $this->http()->get('/instance/connect/'.$this->instanceName($channel))->throw()->json();

        return $this->toQrPayload($response);
    }

    public function connectionState(Channel $channel): string
    {
        $response = $this->http()->get('/instance/connectionState/'.$this->instanceName($channel))->throw()->json();

        return (string) ($response['instance']['state'] ?? 'close');
    }

    public function disconnect(Channel $channel): void
    {
        $instanceName = $this->instanceName($channel);

        // Logout encerra a sessão; delete remove a instância. Erros são tolerados
        // (a instância pode já não existir).
        rescue(fn () => $this->http()->delete('/instance/logout/'.$instanceName), report: false);
        rescue(fn () => $this->http()->delete('/instance/delete/'.$instanceName), report: false);
    }

    // -------------------------------------------------------------------------
    // ChannelAdapter — transporte
    // -------------------------------------------------------------------------

    public function send(Channel $channel, OutboundMessage $message): MessageDispatchResult
    {
        $instanceName = $this->instanceName($channel);
        $number = $message->conversationExternalThreadId;

        try {
            $response = $this->circuitBreaker->for('evolution')->call(function () use ($instanceName, $number, $message) {
                if ($message->media !== null) {
                    return $this->http()->post('/message/sendMedia/'.$instanceName, [
                        'number' => $number,
                        'mediatype' => $this->mediaType($message->media->mimeType),
                        'media' => $message->media->storagePath,
                        'fileName' => $message->media->originalFilename,
                        'caption' => $message->body ?? '',
                    ]);
                }

                return $this->http()->post('/message/sendText/'.$instanceName, [
                    'number' => $number,
                    'text' => $message->body ?? '',
                ]);
            });

            if (! $response->successful()) {
                return new MessageDispatchResult(
                    accepted: false,
                    failureReason: 'Evolution API HTTP '.$response->status(),
                );
            }

            $body = $response->json();

            return new MessageDispatchResult(
                accepted: true,
                externalId: $body['key']['id'] ?? ($body['id'] ?? null),
            );
        } catch (\Throwable $e) {
            return new MessageDispatchResult(accepted: false, failureReason: $e->getMessage());
        }
    }

    /**
     * Para o não oficial não há validação de credenciais por tenant (servidor é
     * nosso); a "validação" é a criação da instância + pareamento por QR.
     *
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(array $credentials): bool
    {
        return true;
    }

    /**
     * Parseia `messages.upsert` do Evolution para o DTO normalizado.
     *
     * @param array<string, mixed> $payload
     */
    public function parseInboundWebhook(array $payload): InboundMessageDto
    {
        $data = $payload['data'] ?? [];
        $remoteJid = (string) ($data['key']['remoteJid'] ?? '');
        $externalThreadId = $this->jidToPhone($remoteJid);

        $message = $data['message'] ?? [];
        [$contentType, $body, $mediaUrls] = $this->extractContent($message);

        return new InboundMessageDto(
            providerType: 'evolution',
            externalThreadId: $externalThreadId,
            externalMessageId: (string) ($data['key']['id'] ?? ''),
            contentType: $contentType,
            body: $body,
            mediaUrls: $mediaUrls,
            rawProviderMetadata: $payload,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function http(): PendingRequest
    {
        $config = config('messaging.providers.evolution');

        return Http::baseUrl((string) ($config['api_url'] ?? ''))
            ->withHeaders(['apikey' => (string) ($config['api_key'] ?? '')])
            ->timeout((int) ($config['http_timeout_seconds'] ?? 15))
            ->acceptJson();
    }

    private function webhookSecret(): string
    {
        return (string) config('messaging.providers.evolution.webhook_secret', '');
    }

    private function webhookUrl(string $instanceName): string
    {
        $base = config('messaging.providers.evolution.webhook_base_url');

        // Base explícita (Docker interno em dev, URL pública em prod). Senão, a
        // rota nomeada (APP_URL) — sempre sob /api/v1 (bug anterior: faltava /api/v1).
        if (is_string($base) && $base !== '') {
            return rtrim($base, '/').'/api/v1/webhooks/evolution/'.$instanceName;
        }

        return route('webhooks.evolution', ['instance' => $instanceName]);
    }

    private function instanceName(Channel $channel): string
    {
        $meta = is_array($channel->provider_metadata) ? $channel->provider_metadata : [];

        return (string) ($meta['instance_name'] ?? "tenant_{$channel->tenant_id}_ch_{$channel->id}");
    }

    /**
     * @param array<string, mixed> $qr
     */
    private function toQrPayload(array $qr): QrPayload
    {
        return new QrPayload(
            base64: $qr['base64'] ?? null,
            code: $qr['code'] ?? null,
            pairingCode: $qr['pairingCode'] ?? null,
        );
    }

    private function jidToPhone(string $jid): string
    {
        // remoteJid: "<phone>@s.whatsapp.net" → "+<phone>"
        $number = explode('@', $jid)[0] ?? $jid;

        return $number !== '' ? '+'.ltrim($number, '+') : $jid;
    }

    /**
     * @param array<string, mixed> $message
     * @return array{0: string, 1: ?string, 2: array<int, string>}
     */
    private function extractContent(array $message): array
    {
        if (isset($message['conversation'])) {
            return ['text', (string) $message['conversation'], []];
        }

        if (isset($message['extendedTextMessage']['text'])) {
            return ['text', (string) $message['extendedTextMessage']['text'], []];
        }

        foreach (['imageMessage' => 'image', 'audioMessage' => 'audio', 'videoMessage' => 'video', 'documentMessage' => 'document'] as $key => $type) {
            if (isset($message[$key])) {
                $caption = $message[$key]['caption'] ?? null;
                $url = $message[$key]['url'] ?? null;

                return [$type, $caption !== null ? (string) $caption : null, $url !== null ? [(string) $url] : []];
            }
        }

        return ['text', null, []];
    }

    private function mediaType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_starts_with($mimeType, 'video/') => 'video',
            default => 'document',
        };
    }
}

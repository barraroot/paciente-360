<?php

namespace App\Domain\Messaging\Channel\Adapters;

use App\Domain\Messaging\Channel\Models\Channel;

/**
 * T048 — Contrato do adapter de canal de mensageria.
 *
 * Abstrai a comunicação com providers externos (Twilio, Meta Graph API, Widget)
 * atrás de uma interface uniforme consumida pelo domínio.
 *
 * Princípio III — Arquitetura em camadas: o domínio (MessageDispatchService,
 * ProcessInboundMessageJob) depende desta interface, nunca do SDK do provider.
 *
 * Implementações:
 *  - WhatsAppCloudAdapter  (Twilio SDK — US1)
 *  - InstagramGraphAdapter (Meta Graph API via Guzzle — US2)
 *  - WebWidgetAdapter      (WebSocket + HTTP interno — US3)
 *
 * @see specs/003-omnichannel-inbox/research.md — R2 (Twilio), R3 (Meta)
 */
interface ChannelAdapter
{
    /**
     * Envia uma mensagem outbound pelo canal.
     *
     * Deve ser chamado dentro de `CircuitBreakerInstance::call()` pelo adapter.
     * Retorna `MessageDispatchResult` com `accepted=false` em vez de lançar
     * exceção quando o provider aceita a mensagem mas reporta erro de entrega.
     *
     * @throws CircuitOpenException quando o circuit breaker está aberto
     */
    public function send(Channel $channel, OutboundMessage $message): MessageDispatchResult;

    /**
     * Valida que as credenciais fornecidas são aceitas pelo provider.
     *
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(array $credentials): bool;

    /**
     * Parseia o payload bruto de um webhook inbound para o DTO normalizado.
     *
     * @param array<string, mixed> $payload
     */
    public function parseInboundWebhook(array $payload): InboundMessageDto;

    /**
     * Retorna o tipo de canal suportado por este adapter.
     *
     * @return 'whatsapp'|'instagram'|'web'
     */
    public function getType(): string;
}

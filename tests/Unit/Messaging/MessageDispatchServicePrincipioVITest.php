<?php

namespace Tests\Unit\Messaging;

use PHPUnit\Framework\TestCase;

/**
 * T101 — Unit test para MessageDispatchService (Princípio VI — janela 24h).
 *
 * Testa isoladamente a lógica de validação de janela 24h WhatsApp/Instagram
 * antes de despachar mensagem. Domain service que ainda não existe (será criado em Lote G).
 *
 * TDD RED: classe MessageDispatchService não existe; testes importam FQN e falham.
 */
class MessageDispatchServicePrincipioVITest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_window_expired_when_last_inbound_older_than_24h_and_not_template(): void
    {
        // Simula:
        // - Conversa WhatsApp com última mensagem inbound há 25 horas
        // - Tentativa de enviar mensagem text (não template)
        // - Deve lançar WindowExpiredWithoutTemplateException

        // Service a ser implementado em Lote G:
        // $service = new \App\Domain\Messaging\Message\Services\MessageDispatchService();
        // $conversation = mock com last_inbound_message_at = 25h atrás;
        // $message = mock com content_type = 'text';
        // $service->send($conversation, $message) -> throws WindowExpiredWithoutTemplateException

        $this->markTestIncomplete(
            'MessageDispatchService não implementado ainda. Teste importa: '
            .'\App\Domain\Messaging\Message\Services\MessageDispatchService'
        );
    }

    /**
     * @test
     */
    public function it_does_not_throw_when_template_message_in_closed_window(): void
    {
        // Conversa com janela 24h fechada, mas content_type='template'
        // Deve passar validação sem exceção

        $this->markTestIncomplete('MessageDispatchService não implementado ainda');
    }

    /**
     * @test
     */
    public function it_does_not_throw_when_text_in_open_window(): void
    {
        // Conversa com última inbound há 30 minutos
        // content_type='text'
        // Deve passar validação (janela aberta)

        $this->markTestIncomplete('MessageDispatchService não implementado ainda');
    }

    /**
     * @test
     */
    public function it_does_not_apply_window_rule_for_web_channel(): void
    {
        // Canal='web'
        // last_inbound > 24h
        // content_type='text'
        // Deve passar validação (web não tem restrição 24h)

        $this->markTestIncomplete('MessageDispatchService não implementado ainda');
    }

    /**
     * @test
     */
    public function it_does_not_apply_window_rule_when_no_inbound_message_yet_first_outbound_kickoff(): void
    {
        // Conversa nova, sem inbound message ainda
        // Atendente inicia contato com template
        // Deve permitir (template inicial é permitido)

        // Ou: conversa nova, atendente envia text sem template
        // Deve bloquear? Spec diz requerimento: "responder apenas dentro de 24h"
        // Implica: primeira mensagem não tem "janela" — precisa template se estamos do lado de fora

        // Interpretação: se no_inbound_yet e content_type='text' -> bloqueia (sem janela aberta)
        // Se content_type='template' -> permite (justamente para kickoff)

        $this->markTestIncomplete('MessageDispatchService não implementado ainda');
    }

    /**
     * @test
     */
    public function it_dispatches_channel_adapter_when_validation_passes(): void
    {
        // Após validação OK, deve chamar channel adapter (Twilio, Meta, Web)
        // para envio real

        $this->markTestIncomplete('MessageDispatchService não implementado ainda');
    }
}

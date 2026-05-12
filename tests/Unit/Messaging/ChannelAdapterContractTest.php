<?php

namespace Tests\Unit\Messaging;

use App\Domain\Messaging\Channel\Adapters\ChannelAdapter;
use App\Domain\Messaging\Channel\Adapters\InboundMessageDto;
use App\Domain\Messaging\Channel\Adapters\MessageDispatchResult;
use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Channel\Models\Channel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * T047 — Contrato da interface ChannelAdapter via ReflectionClass.
 *
 * Valida assinaturas de métodos sem precisar de implementação concreta,
 * garantindo que futuros adapters (WhatsApp, Instagram, Widget) respeitem
 * o contrato definido em T048.
 *
 * Princípio III — Arquitetura em camadas: o domínio depende da interface,
 * não da implementação concreta do provider.
 */
class ChannelAdapterContractTest extends TestCase
{
    private \ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new \ReflectionClass(ChannelAdapter::class);
    }

    #[Test]
    public function it_is_an_interface(): void
    {
        $this->assertTrue($this->reflection->isInterface(), 'ChannelAdapter deve ser uma interface');
    }

    #[Test]
    public function it_defines_send_method_with_correct_signature(): void
    {
        $this->assertTrue(
            $this->reflection->hasMethod('send'),
            'ChannelAdapter deve definir o método send()',
        );

        $method = new ReflectionMethod(ChannelAdapter::class, 'send');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'send() deve ter exatamente 2 parâmetros');

        $this->assertSame('channel', $params[0]->getName());
        $this->assertSame(Channel::class, $params[0]->getType()?->getName());

        $this->assertSame('message', $params[1]->getName());
        $this->assertSame(OutboundMessage::class, $params[1]->getType()?->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'send() deve ter tipo de retorno declarado');
        $this->assertSame(MessageDispatchResult::class, $returnType->getName());
    }

    #[Test]
    public function it_defines_validate_credentials_method(): void
    {
        $this->assertTrue(
            $this->reflection->hasMethod('validateCredentials'),
            'ChannelAdapter deve definir validateCredentials()',
        );

        $method = new ReflectionMethod(ChannelAdapter::class, 'validateCredentials');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'validateCredentials() deve ter 1 parâmetro');
        $this->assertSame('credentials', $params[0]->getName());
        $this->assertSame('array', $params[0]->getType()?->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    #[Test]
    public function it_defines_parse_inbound_webhook_method(): void
    {
        $this->assertTrue(
            $this->reflection->hasMethod('parseInboundWebhook'),
            'ChannelAdapter deve definir parseInboundWebhook()',
        );

        $method = new ReflectionMethod(ChannelAdapter::class, 'parseInboundWebhook');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'parseInboundWebhook() deve ter 1 parâmetro');
        $this->assertSame('payload', $params[0]->getName());
        $this->assertSame('array', $params[0]->getType()?->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame(InboundMessageDto::class, $returnType->getName());
    }

    #[Test]
    public function it_defines_get_type_method(): void
    {
        $this->assertTrue(
            $this->reflection->hasMethod('getType'),
            'ChannelAdapter deve definir getType()',
        );

        $method = new ReflectionMethod(ChannelAdapter::class, 'getType');
        $params = $method->getParameters();

        $this->assertCount(0, $params, 'getType() não deve ter parâmetros');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'getType() deve ter tipo de retorno declarado');
        $this->assertSame('string', $returnType->getName());
    }
}

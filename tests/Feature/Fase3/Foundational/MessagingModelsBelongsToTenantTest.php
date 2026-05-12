<?php

namespace Tests\Feature\Fase3\Foundational;

use App\Domain\Messaging\Assignment\Models\AssignmentRule;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Models\ConversationAssignment;
use App\Domain\Messaging\Infrastructure\Webhook\WebhookEvent;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use App\Models\Concerns\BelongsToTenant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T025 — TDD red: verifica que todos os 11 models de messaging usam
 * BelongsToTenant e que WebhookEvent explicitamente NÃO usa (tenant
 * é resolvido pós-processamento via lookup de channel_id).
 *
 * Princípio II — Multi-tenant isolation (NON-NEGOTIABLE).
 */
class MessagingModelsBelongsToTenantTest extends TestCase
{
    /**
     * @return array<string, array{class: class-string}>
     */
    public static function provideMessagingModels(): array
    {
        return [
            'Channel' => ['class' => Channel::class],
            'ChannelTemplate' => ['class' => ChannelTemplate::class],
            'Conversation' => ['class' => Conversation::class],
            'ConversationAssignment' => ['class' => ConversationAssignment::class],
            'Message' => ['class' => Message::class],
            'MessageMedia' => ['class' => MessageMedia::class],
            'QuickReply' => ['class' => QuickReply::class],
            'WebWidgetConfig' => ['class' => WebWidgetConfig::class],
            'WebWidgetSession' => ['class' => WebWidgetSession::class],
            'AssignmentRule' => ['class' => AssignmentRule::class],
            'UserPresence' => ['class' => UserPresence::class],
        ];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideMessagingModels')]
    #[Test]
    public function messaging_model_uses_belongs_to_tenant(string $class): void
    {
        $this->assertContains(
            BelongsToTenant::class,
            class_uses_recursive($class),
            "Expected {$class} to use the BelongsToTenant trait (Princípio II)."
        );
    }

    #[Test]
    public function webhook_event_does_not_use_belongs_to_tenant(): void
    {
        $this->assertNotContains(
            BelongsToTenant::class,
            class_uses_recursive(WebhookEvent::class),
            'WebhookEvent must NOT use BelongsToTenant — tenant is resolved post-processing via channel_id lookup.'
        );
    }
}

<?php

namespace Tests\Feature\Fase3\Foundational;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T053 — TDD: `messages.body` cifrado em repouso via cast `encrypted`.
 *
 * Research R4: cast `encrypted` Laravel (AES-256-CBC com APP_KEY).
 * - Valor na coluna: base64 da string cifrada (nunca legível como texto claro).
 * - Eloquent decripta transparentemente ao acessar `$message->body`.
 *
 * Princípio I — LGPD: corpo da mensagem NÃO é gravado em claro no banco.
 */
class MessageBodyEncryptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function message_body_is_encrypted_at_rest(): void
    {
        $plainBody = 'João Silva CPF 123.456.789-00 — consulta marcada para amanhã';

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->forTenant($tenant)->create();
        $conversation = Conversation::factory()->forTenant($tenant)->for($channel)->create();

        // Cria via factory passando body em texto claro — o cast cifra automaticamente
        $message = Message::factory()
            ->forTenant($tenant)
            ->for($conversation)
            ->create(['body' => $plainBody]);

        // 1. Lê diretamente do banco (sem Eloquent) → deve ser cifrado
        $rawBody = DB::table('messaging_messages')
            ->where('id', $message->id)
            ->value('body');

        $this->assertNotNull($rawBody, 'body não pode ser null no banco');
        $this->assertNotSame(
            $plainBody,
            $rawBody,
            'body deve estar cifrado no banco (não igual ao texto claro)',
        );

        // Cast encrypted do Laravel serializa com base64 — a string cifrada
        // contém separadores ':' e é base64 dentro de eyJ... (JSON wrappado)
        // Verificamos que NÃO é texto legível contendo o conteúdo da mensagem
        $this->assertStringNotContainsString(
            'João Silva',
            $rawBody,
            'Texto claro NÃO pode aparecer na coluna body cifrada',
        );

        $this->assertStringNotContainsString(
            '123.456.789-00',
            $rawBody,
            'CPF NÃO pode aparecer em claro na coluna body',
        );

        // 2. Lê via Eloquent → cast decripta transparentemente
        $freshMessage = Message::withoutGlobalScopes()->find($message->id);

        $this->assertSame(
            $plainBody,
            $freshMessage->body,
            'Eloquent deve decriptar body transparentemente via cast encrypted',
        );
    }

    #[Test]
    public function message_body_null_is_stored_as_null(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->forTenant($tenant)->create();
        $conversation = Conversation::factory()->forTenant($tenant)->for($channel)->create();

        $message = Message::factory()
            ->forTenant($tenant)
            ->for($conversation)
            ->image()  // estado 'image' seta body=null
            ->create();

        $rawBody = DB::table('messaging_messages')
            ->where('id', $message->id)
            ->value('body');

        $this->assertNull($rawBody, 'body null deve ser armazenado como null');
        $this->assertNull($message->body, 'Eloquent deve retornar null para body null');
    }
}

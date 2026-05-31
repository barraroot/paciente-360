<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **T019 (Fase 18 — Polish T200-T207, FR-008b/c)** — colunas de cooldown em
 * messaging_conversations.
 *
 * Rate limit anti-abuso 2 camadas (Q-clarify-5=C). Excedido → CooldownService
 * popula `cooldown_until` (timestamp futuro). Durante o cooldown:
 *   - ProcessAiResponseJob no-op
 *   - KanbanCurationService no-op
 *   - AudioSynthesisService no-op
 *   - McpToolBridge no-op
 * Operador encerra manualmente OU expira automaticamente (config
 * messaging.cooldown.minutes — default 15min).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_conversations', function (Blueprint $table): void {
            $table->timestampTz('cooldown_until')->nullable();
            $table->string('cooldown_reason', 80)->nullable();
        });

        DB::statement('CREATE INDEX messaging_conversations_cooldown_idx ON messaging_conversations (cooldown_until) WHERE cooldown_until IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('messaging_conversations', function (Blueprint $table): void {
            $table->dropColumn(['cooldown_until', 'cooldown_reason']);
        });
    }
};

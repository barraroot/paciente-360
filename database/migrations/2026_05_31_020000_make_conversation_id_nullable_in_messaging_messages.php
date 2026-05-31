<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **T183-aux (Fase 18 — US6, FR-040/042)** — `conversation_id` torna-se
 * NULLABLE para suportar mensagens de Persona Test Session (sandbox) que
 * não pertencem a uma conversa real.
 *
 * Mensagens com `sandbox=true` + `sandbox_session_id IS NOT NULL` podem
 * ter `conversation_id IS NULL`. Agregadores existentes já filtram por
 * `sandbox=false` (T186), então não há impacto nas queries de produção.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE messaging_messages ALTER COLUMN conversation_id DROP NOT NULL');

        // CHECK adicional: mensagens com conversation_id NULL DEVEM ser sandbox.
        DB::statement(<<<'SQL'
            ALTER TABLE messaging_messages
            ADD CONSTRAINT messaging_messages_sandbox_or_conversation_check
            CHECK (conversation_id IS NOT NULL OR sandbox = true)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE messaging_messages DROP CONSTRAINT IF EXISTS messaging_messages_sandbox_or_conversation_check');
        // Set NOT NULL exige que não haja linhas com NULL — esta down é best-effort.
        // Em prod com dados sandbox, a reversão exigiria limpeza manual.
    }
};

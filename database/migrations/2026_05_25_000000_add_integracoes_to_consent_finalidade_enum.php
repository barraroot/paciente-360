<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **T188-pre (Fase 8 — Lote D US-11.1)** — Adiciona `integracoes` ao enum.
 *
 * Necessário para a finalidade de consentimento de compartilhamento com
 * integrações externas (webhooks / API pública). Q17 — payload mascarado
 * `<consent_withheld>` quando ausente.
 *
 * Postgres não permite `DROP VALUE` em enum — `down()` é no-op explícito.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_enum
                    WHERE enumlabel = 'integracoes'
                      AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'consent_finalidade_enum')
                ) THEN
                    ALTER TYPE consent_finalidade_enum ADD VALUE 'integracoes';
                END IF;
            END$$;
        SQL);
    }

    public function down(): void
    {
        // Postgres não suporta DROP VALUE em enum. Reversão exigiria DROP TYPE
        // + recriação preservando dados — intencionalmente não-implementado.
    }
};

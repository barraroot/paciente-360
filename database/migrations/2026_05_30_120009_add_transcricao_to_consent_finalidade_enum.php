<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **T009 (Fase 18 — Q-clarify-2=B)** — adiciona `transcricao` ao enum
 * `consent_finalidade_enum`.
 *
 * Justificativa LGPD: retenção do áudio bruto inbound (US4) ALÉM do prazo
 * padrão das mídias (Fase 13) configura nova finalidade de tratamento
 * (auditoria de qualidade, treinamento interno, revisão de incidentes).
 * Sem este consentimento, o áudio bruto é purgado no prazo padrão
 * (PurgeExpiredAudioRawJob — T210); apenas a transcrição em texto permanece.
 *
 * STT/TTS em si reusam a finalidade `transacional` já existente (são meios
 * técnicos do mesmo propósito comunicacional iniciado pelo paciente).
 *
 * Postgres não permite DROP VALUE em enum — `down()` é no-op explícito.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_enum
                    WHERE enumlabel = 'transcricao'
                      AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'consent_finalidade_enum')
                ) THEN
                    ALTER TYPE consent_finalidade_enum ADD VALUE 'transcricao';
                END IF;
            END$$;
        SQL);
    }

    public function down(): void
    {
        // Postgres não suporta DROP VALUE em enum. Reversão exigiria
        // DROP TYPE + recriação preservando dados — não-implementado.
    }
};

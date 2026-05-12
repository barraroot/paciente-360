<?php

namespace App\Domain\Messaging\Infrastructure\Webhook;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * T051 — Gravação idempotente de eventos de webhook.
 *
 * Implementa o gate de idempotência do research R9:
 *  - `INSERT OR IGNORE` via UNIQUE (provider, external_id)
 *  - `raw_payload_encrypted` cifrado via Crypt::encryptString (AES-256-CBC + APP_KEY)
 *  - Retorna dados do registro se novo; null se duplicata (evita re-despacho do job)
 *
 * Uso nos webhook controllers:
 * ```php
 * $row = $this->recorder->recordOnce('twilio', $sid, $payload, $verified, 'message.inbound');
 * if ($row === null) {
 *     return response()->json(['ok' => true]); // 200 para Twilio parar retry
 * }
 * ProcessInboundMessageJob::dispatch($row['id']);
 * ```
 *
 * Princípio I — LGPD: payload bruto pode conter PII (número de telefone, corpo da mensagem).
 * Cifragem garante que dump do banco não expõe conteúdo.
 *
 * @see specs/003-omnichannel-inbox/research.md — R9
 */
final class WebhookEventRecorder
{
    /**
     * Registra um evento de webhook de forma idempotente.
     *
     * @param array<string, mixed> $payload Payload bruto do provider
     * @return array<string, mixed>|null Dados do registro inserido, ou null se duplicata
     */
    public function recordOnce(
        string $provider,
        string $externalId,
        array $payload,
        bool $signatureVerified,
        string $eventType,
    ): ?array {
        $encryptedPayload = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));

        $inserted = DB::table('messaging_webhook_events')->insertOrIgnore([
            'provider' => $provider,
            'external_id' => $externalId,
            'event_type' => $eventType,
            'raw_payload_encrypted' => $encryptedPayload,
            'signature_verified' => $signatureVerified,
            'status' => 'received',
            'attempts' => 0,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return null;
        }

        /** @var object $row */
        $row = DB::table('messaging_webhook_events')
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        return $row !== null ? (array) $row : null;
    }
}

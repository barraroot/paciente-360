<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Messaging\Message\Models\Message;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * T079 — Status callback do Twilio (delivered/read/failed/sent).
 *
 * Atualiza o status de mensagens outbound com base nos callbacks do Twilio.
 * Responde 200 sempre — erros tratados silenciosamente (idempotente).
 *
 * Princípio V — log estruturado com message_id, status, external_id.
 */
class TwilioStatusCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $messageSid = $request->input('MessageSid', '');
        $messageStatus = $request->input('MessageStatus', '');

        /** @var Message|null $message */
        $message = Message::withoutGlobalScopes()
            ->where('external_id', $messageSid)
            ->first();

        if ($message === null) {
            // Retorna 200 silenciosamente — idempotência Twilio
            return response()->json(['ok' => true]);
        }

        $updates = ['status' => $messageStatus];

        match ($messageStatus) {
            'sent' => $updates['sent_at'] = now(),
            'delivered' => $updates['delivered_at'] = now(),
            'read' => $updates['read_at'] = now(),
            'failed' => $updates['failure_reason'] = $this->buildFailureReason($request),
            default => null,
        };

        $message->update($updates);

        Log::info('webhook.twilio.status.updated', [
            'message_id' => $message->id,
            'external_id' => $messageSid,
            'status' => $messageStatus,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Constrói a string de motivo de falha a partir do callback Twilio.
     */
    private function buildFailureReason(Request $request): string
    {
        $errorCode = $request->input('ErrorCode', '');
        $errorMessage = $request->input('ErrorMessage', '');

        if (! empty($errorCode) && ! empty($errorMessage)) {
            return "[{$errorCode}] {$errorMessage}";
        }

        if (! empty($errorMessage)) {
            return $errorMessage;
        }

        if (! empty($errorCode)) {
            return "Error code: {$errorCode}";
        }

        return 'Unknown failure';
    }
}

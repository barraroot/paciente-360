<?php

namespace App\Http\Middleware;

use App\Domain\Messaging\Channel\Models\Channel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * T080 — Middleware de validação de assinatura HMAC do Twilio.
 *
 * O Twilio assina cada requisição com HMAC SHA1 do URL + parâmetros
 * usando o auth_token do account como chave. Este middleware:
 *
 *  1. Extrai o header `X-Twilio-Signature`
 *  2. Identifica o canal pelo `MessagingServiceSid` no payload (inbound)
 *     ou tenta todos os canais ativos (status callback)
 *  3. Valida a assinatura com o auth_token do canal
 *  4. Rejeita com 403 se inválida
 *
 * Princípio I — Segurança: webhooks sem assinatura válida nunca chegam ao controller.
 *
 * @see https://www.twilio.com/docs/usage/webhooks/webhooks-security
 */
class ValidateTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Twilio-Signature');

        if (empty($signature)) {
            Log::warning('ValidateTwilioSignature: header ausente', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json(['error' => 'missing_signature'], 403);
        }

        $url = $request->fullUrl();
        $params = $request->all();

        // Para multi-tenant: identifica o canal pelo MessagingServiceSid (inbound webhook)
        $messagingServiceSid = $request->input('MessagingServiceSid');

        if ($messagingServiceSid !== null) {
            $channel = Channel::withoutGlobalScopes()
                ->where('type', 'whatsapp')
                ->whereJsonContains('provider_metadata->messaging_service_sid', $messagingServiceSid)
                ->first();

            if ($channel !== null) {
                $authToken = $this->authTokenFor($channel);

                if ($this->validateSignature($signature, $url, $params, $authToken)) {
                    return $next($request);
                }

                Log::warning('ValidateTwilioSignature: assinatura inválida (channel-specific)', [
                    'channel_id' => $channel->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'invalid_signature'], 403);
            }
        }

        // Status callback ou inbound sem MessagingServiceSid:
        // Tenta validar com auth_token de qualquer canal whatsapp ativo.
        // Twilio usa o mesmo auth_token para todos os callbacks de uma account.
        if ($this->validateAgainstAnyActiveChannel($signature, $url, $params)) {
            return $next($request);
        }

        // Fallback: token global de configuração
        $globalAuthToken = config('messaging.providers.twilio.auth_token', '');

        if (! empty($globalAuthToken) && $this->validateSignature($signature, $url, $params, $globalAuthToken)) {
            return $next($request);
        }

        Log::warning('ValidateTwilioSignature: assinatura inválida', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'full_url' => $url,
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'x_forwarded_proto' => $request->header('X-Forwarded-Proto'),
            'param_count' => count($params),
            'has_messaging_service_sid' => $messagingServiceSid !== null,
        ]);

        return response()->json(['error' => 'invalid_signature'], 403);
    }

    /**
     * Tenta validar a assinatura contra todos os canais WhatsApp ativos.
     * Usado quando não há MessagingServiceSid no payload (ex.: status callbacks).
     *
     * @param array<string, mixed> $params
     */
    private function validateAgainstAnyActiveChannel(string $signature, string $url, array $params): bool
    {
        try {
            $channels = Channel::withoutGlobalScopes()
                ->where('type', 'whatsapp')
                ->whereIn('status', ['ativo', 'active'])
                ->limit(50)
                ->get();

            foreach ($channels as $channel) {
                $authToken = $this->authTokenFor($channel);

                if ($this->validateSignature($signature, $url, $params, $authToken)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Tabela pode não existir em ambiente de teste (schema parcial).
            // Retorna false — o middleware já trata o fallback.
        }

        return false;
    }

    /**
     * Extrai auth_token das credenciais descriptografadas do canal.
     *
     * O cast 'encrypted' do Channel usa encrypt/decrypt sem serialização.
     * O factory pré-encripta com Crypt::encrypt($array) (com serialização),
     * então credentials_encrypted via model já é a string JSON do envelope
     * Crypt::encrypt($array). Chamamos Crypt::decrypt() com $unserialize=true
     * para obter o array original.
     */
    private function authTokenFor(Channel $channel): string
    {
        try {
            $raw = $channel->credentials_encrypted;

            if (is_array($raw)) {
                return (string) ($raw['auth_token'] ?? '');
            }

            if (is_string($raw)) {
                // credentials_encrypted via model = Crypt::encrypt($array, true) envelope.
                // Crypt::decrypt() com $unserialize=true recupera o array original.
                $decoded = Crypt::decrypt($raw);

                if (is_array($decoded)) {
                    return (string) ($decoded['auth_token'] ?? '');
                }
            }

            return '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Valida a assinatura HMAC SHA1 do Twilio.
     *
     * @param array<string, mixed> $params
     */
    private function validateSignature(string $signature, string $url, array $params, string $authToken): bool
    {
        if (empty($authToken)) {
            return false;
        }

        try {
            $validator = new RequestValidator($authToken);

            return $validator->validate($signature, $url, $params);
        } catch (\Exception) {
            return false;
        }
    }
}

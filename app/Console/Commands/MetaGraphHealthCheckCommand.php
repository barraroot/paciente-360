<?php

namespace App\Console\Commands;

use App\Domain\Messaging\Channel\Adapters\InstagramGraphAdapter;
use App\Domain\Messaging\Channel\Models\Channel;
use GuzzleHttp\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * T196 — Health check para canais Instagram via Meta Graph API.
 *
 * Verifica compatibilidade de versão da Graph API (research R7).
 * Para cada canal Instagram ativo: chama GET /me?access_token= e verifica
 * se a versão no header X-Fb-Api-Version bate com a configurada.
 *
 * Agendado mensalmente via console.php.
 *
 * @see specs/003-omnichannel-inbox/research.md — R7 (versionamento pinned)
 */
#[Signature('messaging:meta-health-check')]
#[Description('Verifica saúde dos canais Instagram contra Meta Graph API e detecta incompatibilidade de versão')]
class MetaGraphHealthCheckCommand extends Command
{
    public function handle(InstagramGraphAdapter $adapter): int
    {
        $configuredVersion = config('messaging.providers.meta.graph_api_version', 'v21.0');

        $channels = Channel::withoutGlobalScopes()
            ->where('type', 'instagram')
            ->where('status', 'ativo')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('Nenhum canal Instagram ativo encontrado.');

            return self::SUCCESS;
        }

        $this->info("Verificando {$channels->count()} canal(is) Instagram (versão configurada: {$configuredVersion})...");

        $hasErrors = false;

        foreach ($channels as $channel) {
            $credentials = $this->decryptCredentials($channel);
            $token = (string) ($credentials['page_access_token'] ?? '');

            if ($token === '') {
                $this->warn("Canal #{$channel->id}: page_access_token ausente — pulando.");

                continue;
            }

            try {
                $httpClient = new Client(['timeout' => 10]);
                $version = $configuredVersion;

                $response = $httpClient->get(
                    "https://graph.facebook.com/{$version}/me",
                    ['query' => ['access_token' => $token]]
                );

                $statusCode = $response->getStatusCode();
                $apiVersionHeader = $response->getHeaderLine('X-Fb-Api-Version');

                // Verifica mismatch de versão
                if ($apiVersionHeader !== '' && $apiVersionHeader !== $configuredVersion) {
                    $message = "Canal #{$channel->id}: versão de API mismatched. Configurado={$configuredVersion}, Header={$apiVersionHeader}";
                    $this->warn($message);
                    Log::warning('MetaGraphHealthCheck: version mismatch', [
                        'channel_id' => $channel->id,
                        'configured_version' => $configuredVersion,
                        'api_version_header' => $apiVersionHeader,
                    ]);
                    $hasErrors = true;
                } elseif ($statusCode === 200) {
                    $this->info("Canal #{$channel->id} ({$channel->name}): OK (status {$statusCode})");
                } else {
                    $this->error("Canal #{$channel->id}: status inesperado {$statusCode}");
                    $hasErrors = true;
                }

                // Atualiza last_health_check_at
                $channel->update(['last_health_check_at' => now()]);

            } catch (\Throwable $e) {
                $this->error("Canal #{$channel->id}: exceção — {$e->getMessage()}");
                Log::error('MetaGraphHealthCheck: exceção', [
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ]);
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $this->warn('Health check concluído com alertas. Verifique os logs.');

            return self::FAILURE;
        }

        $this->info('Health check concluído. Todos os canais OK.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptCredentials(Channel $channel): array
    {
        $raw = $channel->credentials_encrypted;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            try {
                $decoded = Crypt::decrypt($raw);

                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }
}

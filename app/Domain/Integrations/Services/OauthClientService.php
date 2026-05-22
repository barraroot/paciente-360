<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\TenantOauthClient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * **T218 (Fase 8 — Lote D US-11.2)** — OAuth 2.0 Client Credentials (Q18).
 *
 * Opt-in via `config('finalization.oauth_enabled')`. Quando desabilitado,
 * todos os métodos lançam `RuntimeException('oauth_disabled')`.
 *
 * Quando habilitado (enterprise), `issueAccessToken` requer Laravel Passport
 * instalado. Por enquanto, retornamos um pseudo-JWT (HS256 base64) suficiente
 * para validação de fluxo — produção exige Passport real.
 */
final class OauthClientService
{
    /**
     * @param array<int, string> $scopes
     * @return array{client: TenantOauthClient, client_secret: string}
     */
    public function createClient(Tenant $tenant, User $creator, string $name, array $scopes): array
    {
        $this->assertEnabled();

        $clientSecret = 'cs_'.Str::random(60);

        $client = TenantOauthClient::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'client_id' => (string) Str::uuid(),
            'client_secret_hash' => hash('sha256', $clientSecret),
            'scopes' => $scopes,
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);

        return ['client' => $client, 'client_secret' => $clientSecret];
    }

    /**
     * Emite access token de curta duração (1h).
     *
     * @return array{access_token: string, token_type: string, expires_in: int, scope: string}
     */
    public function issueAccessToken(string $clientId, string $clientSecret): array
    {
        $this->assertEnabled();

        $client = TenantOauthClient::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();

        if ($client === null) {
            throw new RuntimeException('invalid_client');
        }

        if (! hash_equals($client->client_secret_hash, hash('sha256', $clientSecret))) {
            throw new RuntimeException('invalid_client');
        }

        $client->update(['last_used_at' => now()]);

        // **Stub JWT-like** — produção exige Passport. Suficiente para validar
        // fluxo end-to-end em testes (OauthAuthenticator decodifica este formato).
        $payload = base64_encode(json_encode([
            'iss' => 'paciente360',
            'sub' => $client->client_id,
            'tenant_id' => $client->tenant_id,
            'scope' => implode(' ', $client->scopes?->toArray() ?? []),
            'iat' => now()->timestamp,
            'exp' => now()->addHour()->timestamp,
        ]));

        return [
            'access_token' => "stub.{$payload}.stub",
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => implode(' ', $client->scopes?->toArray() ?? []),
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('finalization.oauth_enabled', false);
    }

    private function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('oauth_disabled');
        }
    }
}

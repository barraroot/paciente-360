<?php

declare(strict_types=1);

namespace App\Domain\Ai\Persona\Services;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Persona\Models\PersonaTestSession;
use App\Events\Ai\Persona\PersonaTestSessionClosed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * **T175-T177 (Fase 18 — US6, FR-038/039/043/044/051)** — gerencia sessões
 * de chat sandbox de Persona.
 *
 * Comportamentos:
 *  - `open(User, AiPersona, ?array $personaDraft)` — auto-fecha sessão `open`
 *    anterior do mesmo (admin, persona) com motivo `superseded`; emite Sanctum
 *    PAT com abilities `['mcp.invoke', 'mcp.sandbox']` + tenant_id; persiste
 *    `persona_snapshot` (versão publicada OU draft per FR-039).
 *  - `close(PersonaTestSession)` — marca `status='closed'` + `closed_at=now`
 *    + revoga o PAT correspondente (efeito imediato — FR-051).
 *  - `archive(PersonaTestSession)` — `status='archived'`.
 *
 * Resultado da `open()` carrega `plainTextToken` (devolvido UMA vez — não
 * recuperável depois — FR-046).
 */
final class PersonaTestSessionService
{
    /**
     * @param array<string, mixed>|null $personaDraft versão NÃO publicada do form (FR-039)
     */
    public function open(User $admin, AiPersona $persona, ?array $personaDraft = null): PersonaTestSessionOpenResult
    {
        return DB::transaction(function () use ($admin, $persona, $personaDraft): PersonaTestSessionOpenResult {
            // Auto-fecha sessão `open` anterior do mesmo (admin, persona) — FR-043 (1 por par).
            $previous = PersonaTestSession::query()
                ->where('admin_user_id', $admin->id)
                ->where('persona_id', $persona->id)
                ->where('status', 'open')
                ->first();

            if ($previous !== null) {
                $this->close($previous, supersede: true);
            }

            // Snapshot — draft vence publicação quando enviado (FR-039).
            $snapshot = $personaDraft !== null
                ? $personaDraft
                : $this->snapshotFromModel($persona);

            // Cria sessão (Eloquent emite UUID via HasUuids).
            $session = PersonaTestSession::create([
                'tenant_id' => $persona->tenant_id,
                'persona_id' => $persona->id,
                'persona_snapshot' => $snapshot,
                'admin_user_id' => $admin->id,
                'status' => 'open',
            ]);

            // Mint Sanctum PAT scoped sandbox (FR-040/041 — SandboxNeutralizer
            // detecta ability `mcp.sandbox` na credencial).
            $raw = Str::random(40);
            $hash = hash('sha256', $raw);
            $token = new PersonalAccessToken;
            $token->forceFill([
                'tokenable_type' => User::class,
                'tokenable_id' => $admin->id,
                'tenant_id' => $persona->tenant_id,
                'name' => "persona-test-session:{$session->id}",
                'token' => $hash,
                'abilities' => ['mcp.invoke', 'mcp.sandbox'],
                'expires_at' => null, // revogado explicitamente no close()
            ])->save();

            $session->forceFill(['mcp_token_id' => $token->id])->save();

            $plainTextToken = $token->id.'|'.$raw;

            return new PersonaTestSessionOpenResult(
                session: $session->refresh(),
                plainTextToken: $plainTextToken,
                echoChannel: "private-persona-test.{$session->id}",
            );
        });
    }

    public function close(PersonaTestSession $session, bool $supersede = false): PersonaTestSession
    {
        if ($session->status !== 'open') {
            return $session; // idempotente
        }

        DB::transaction(function () use ($session): void {
            // Revoga PAT — efeito imediato (FR-051).
            if ($session->mcp_token_id !== null) {
                try {
                    PersonalAccessToken::query()->whereKey($session->mcp_token_id)->delete();
                } catch (Throwable) {
                    // Token pode já ter expirado/sido removido — não bloqueia.
                }
            }

            $session->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'mcp_token_id' => null,
            ])->save();
        });

        $fresh = $session->refresh();

        // Evento auditável — broadcasta o fim da sessão (FR-051).
        PersonaTestSessionClosed::dispatch($fresh, $supersede ? 'superseded' : 'user_closed');

        return $fresh;
    }

    public function archive(PersonaTestSession $session): PersonaTestSession
    {
        if ($session->status === 'archived') {
            return $session;
        }
        if ($session->status === 'open') {
            $this->close($session);
        }

        $session->forceFill([
            'status' => 'archived',
            'archived_at' => now(),
        ])->save();

        return $session->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromModel(AiPersona $persona): array
    {
        return [
            'id' => $persona->id,
            'name' => $persona->name,
            'markdown_content' => $persona->markdown_content,
            'tone' => $persona->tone,
            'objective' => $persona->objective,
            'limitations' => $persona->limitations,
            'initial_message' => $persona->initial_message,
            'fallback_message' => $persona->fallback_message,
            'handoff_rules' => $persona->handoff_rules,
            'voice_id' => $persona->voice_id,
            'ai_model_id' => $persona->ai_model_id,
        ];
    }
}

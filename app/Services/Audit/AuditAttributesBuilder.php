<?php

namespace App\Services\Audit;

use App\Events\Contracts\Auditable;
use Illuminate\Support\Str;

/**
 * Extrai e normaliza os atributos de um evento `Auditable` para inserção
 * em `audit_logs`.
 *
 * Centraliza a lógica de extração para que tanto o `PersistAuditLogListener`
 * quanto o `AuditService::log()` produzam entradas idênticas (critério T074).
 *
 * Sanitização de payload (Princípio I — LGPD minimização de dados):
 * Keys sensíveis conhecidas são removidas antes da persistência para evitar
 * que credenciais e tokens apareçam em registros de auditoria.
 *
 * O `request_id` é obtido do container (`app('request_id')`) quando o
 * middleware `LogStructuredRequestData` o injetou via `app()->instance()`,
 * com fallback no header `X-Request-Id` da request atual.
 *
 * @see App\Listeners\PersistAuditLogListener
 * @see App\Services\Audit\AuditService
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
final class AuditAttributesBuilder
{
    /**
     * Keys de payload removidas antes da persistência.
     * Princípio I — LGPD: minimização de dados pessoais/sensíveis.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'remember_token',
        'secret',
        'api_key',
        'access_token',
        'refresh_token',
    ];

    /**
     * Constrói o array de atributos para `AuditLog::create()`.
     *
     * @return array<string, mixed>
     */
    public function build(Auditable $event): array
    {
        $userId = $event->auditUserId();
        $actorType = $this->resolveActorType($event, $userId);

        $auditable = $event->auditableModel();

        return [
            'tenant_id' => $event->auditTenantId() ?? $this->resolveTenantId(),
            'user_id' => $userId,
            'actor_type' => $actorType,
            'action' => $event->auditAction(),
            'auditable_type' => $auditable !== null ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'payload' => $this->sanitizePayload($event->auditPayload()),
            'ip' => $this->resolveIp(),
            'user_agent' => $this->resolveUserAgent(),
            'request_id' => $this->resolveRequestId(),
        ];
    }

    /**
     * Resolve o `actor_type`:
     *  1. Se o evento sobrescreve via `auditActorType()`, usa o valor.
     *  2. Caso contrário, infere: `'user'` se `user_id !== null`, `'system'` otherwise.
     */
    private function resolveActorType(Auditable $event, ?int $userId): string
    {
        $override = $event->auditActorType();

        if ($override !== null && in_array($override, ['user', 'system', 'webhook'], true)) {
            return $override;
        }

        return $userId !== null ? 'user' : 'system';
    }

    /**
     * Resolve `tenant_id` do container quando o evento retorna `null`.
     */
    private function resolveTenantId(): ?int
    {
        if (! app()->bound('tenant')) {
            return null;
        }

        $tenant = app('tenant');

        return (is_object($tenant) && isset($tenant->id)) ? (int) $tenant->id : null;
    }

    /**
     * Resolve o `request_id`:
     *  1. Container `app('request_id')` — injetado pelo `LogStructuredRequestData`.
     *  2. Header `X-Request-Id` da request atual (fallback).
     *  3. `null` em contexto CLI/job sem request.
     */
    private function resolveRequestId(): ?string
    {
        if (app()->bound('request_id')) {
            return (string) app('request_id');
        }

        return request()?->headers->get('X-Request-Id');
    }

    /**
     * Resolve o IP da request atual; `null` em contexto CLI/job.
     */
    private function resolveIp(): ?string
    {
        return request()?->ip();
    }

    /**
     * Resolve o `user_agent` limitado a 500 caracteres.
     * Limite respeita a coluna `VARCHAR(500)` do schema.
     */
    private function resolveUserAgent(): ?string
    {
        $ua = request()?->userAgent();

        if ($ua === null) {
            return null;
        }

        return Str::limit($ua, 500, '');
    }

    /**
     * Remove chaves sensíveis e mascara CPF recursivamente.
     *
     * Princípio I — LGPD: minimização de dados.
     *  - Credenciais e tokens NUNCA devem aparecer em registros de auditoria.
     *  - CPF NUNCA é gravado em claro — máscara `***.***.***-XX` preserva
     *    apenas os 2 últimos dígitos para suporte forense (T033 / Fase 2).
     *
     * Regra de máscara CPF: aplicada quando a chave **é exatamente** `cpf`
     * ou **contém** `_cpf` ou `cpf_` (ex.: `documento_cpf`, `cpf_titular`).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $sensitiveSet = array_flip(self::SENSITIVE_KEYS);
        $result = [];

        foreach ($payload as $key => $value) {
            if (isset($sensitiveSet[$key])) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->sanitizePayload($value);

                continue;
            }

            if (self::isCpfKey($key) && $value !== null) {
                $result[$key] = self::maskCpf((string) $value);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Determina se a chave do payload representa um CPF que deve ser mascarado.
     */
    private static function isCpfKey(int|string $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $lower = strtolower($key);

        return $lower === 'cpf'
            || str_contains($lower, '_cpf')
            || str_contains($lower, 'cpf_');
    }

    /**
     * Máscara `***.***.***-XX`. Preserva os 2 últimos dígitos numéricos
     * (ou usa `**` se a entrada não tiver 2 dígitos numéricos disponíveis).
     */
    private static function maskCpf(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) >= 2) {
            return '***.***.***-'.substr($digits, -2);
        }

        return '***.***.***-**';
    }
}

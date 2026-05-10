<?php

namespace App\Support\Csv;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Exportador de CSV streaming com proteção contra CSV/Excel formula injection
 * (US-2.4 — FR-037, Princípio I — LGPD/segurança).
 *
 * Estratégia:
 *  - Streaming via `php://output` para suportar grandes resultados sem
 *    estourar memória.
 *  - Prefixo `\xEF\xBB\xBF` (UTF-8 BOM) — Excel detecta automaticamente.
 *  - `escapeFormulaInjection()`: prefixa apóstrofo `'` em valores que
 *    iniciam com `= + - @ \t \r` (vetor "Excel/LibreOffice formula
 *    injection"; ver OWASP CSV Injection).
 *  - `fputcsv` em modo RFC 4180 (escape param vazio) cobre escape (vírgulas,
 *    aspas, newlines dentro de células).
 *
 * Usado pelo `AuditLogsController::export`.
 *
 * @see App\Http\Controllers\Api\V1\Audit\AuditLogsController
 */
final class CsvExporter
{
    /**
     * Caracteres iniciais perigosos (formula injection).
     *
     * @var list<string>
     */
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Colunas default exportadas para `audit_logs` (mesma ordem do header).
     *
     * @var list<string>
     */
    public const AUDIT_LOG_COLUMNS = [
        'id',
        'created_at',
        'tenant_id',
        'actor_type',
        'user_id',
        'user_name',
        'user_email',
        'action',
        'auditable_type',
        'auditable_id',
        'ip',
        'user_agent',
        'request_id',
        'payload',
    ];

    /**
     * Faz streaming do CSV de audit logs para `php://output`.
     *
     * Caller deve estar dentro de `response()->streamDownload()` para que
     * o output buffer seja flushed corretamente.
     *
     * @param Builder<AuditLog> $query Builder já filtrado (sem paginação).
     */
    public function streamAuditLogs(Builder $query): void
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            return;
        }

        // UTF-8 BOM para que Excel/LibreOffice abram com encoding correto.
        fwrite($out, "\xEF\xBB\xBF");

        // Cabeçalho (sem escape — colunas são identificadores controlados).
        fputcsv($out, self::AUDIT_LOG_COLUMNS, ',', '"', '');

        // Streaming row-a-row via `lazy()` (cursor).
        $query->lazy(500)->each(function (AuditLog $log) use ($out): void {
            fputcsv($out, $this->auditLogRow($log), ',', '"', '');
        });

        fclose($out);
    }

    /**
     * Constrói a row de CSV a partir de um `AuditLog`, aplicando escape
     * contra formula injection em cada célula textual.
     *
     * `payload` é serializado como JSON (compact, sem pretty-print) e
     * passado pelo escaper.
     *
     * @return list<string>
     */
    public function auditLogRow(AuditLog $log): array
    {
        $user = $log->relationLoaded('user') ? $log->user : null;

        $cells = [
            (string) $log->id,
            $log->created_at?->toIso8601String() ?? '',
            $log->tenant_id !== null ? (string) $log->tenant_id : '',
            (string) $log->actor_type,
            $log->user_id !== null ? (string) $log->user_id : '',
            (string) ($user?->name ?? ''),
            (string) ($user?->email ?? ''),
            (string) $log->action,
            (string) ($log->auditable_type ?? ''),
            $log->auditable_id !== null ? (string) $log->auditable_id : '',
            (string) ($log->ip ?? ''),
            (string) ($log->user_agent ?? ''),
            (string) ($log->request_id ?? ''),
            $this->encodePayload($log->payload),
        ];

        return array_map([$this, 'escapeFormulaInjection'], $cells);
    }

    /**
     * Aplica escape contra CSV/Excel formula injection.
     *
     * Quando o valor começa com `=`, `+`, `-`, `@`, TAB ou CR, prefixa
     * um apóstrofo `'` para que o software de planilhas trate como texto
     * em vez de fórmula.
     *
     * `null` → string vazia; `''` → `''` (sem prefixo).
     */
    public function escapeFormulaInjection(?string $value): string
    {
        if ($value === null || $value === '') {
            return (string) $value;
        }

        $first = $value[0];

        if (in_array($first, self::DANGEROUS_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Serializa o payload (array) como JSON compact-safe. Mantém UTF-8
     * sem escape de slashes/unicode para legibilidade pós-export.
     *
     * @param array<string, mixed>|null $payload
     */
    private function encodePayload(?array $payload): string
    {
        if ($payload === null || $payload === []) {
            return '';
        }

        return (string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}

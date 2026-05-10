<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast para colunas JSONB do PostgreSQL que precisam ser tratadas como array PHP.
 *
 * O driver PDO do PostgreSQL serializa o valor default `'{}'::jsonb` como a
 * string `"{}"` (JSON string, não JSON object), o que faz o cast padrão
 * `'array'` do Eloquent retornar uma string em vez de array. Este cast
 * normaliza o valor aplicando dois rounds de `json_decode` quando necessário.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>>
 */
final class AsJsonArray implements CastsAttributes
{
    /**
     * Converte o valor do banco para array PHP.
     * Handles: `null`, `[]`, `{}`, `"{}"` (PostgreSQL JSONB default serialization).
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '' || $value === '{}') {
            return [];
        }

        $decoded = json_decode($value, true);

        // Caso o PostgreSQL retorne um JSON string (ex.: `"{}"`) em vez de
        // um JSON object — aplicar decode novamente.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prepara o valor PHP para persistência no banco como JSON.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE);
    }
}

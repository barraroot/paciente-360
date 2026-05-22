<?php

declare(strict_types=1);

namespace App\Support\Lgpd;

/**
 * **T014** — Mapa explícito de anonimização do Direito ao Esquecimento (Q26).
 *
 * Define, por campo do paciente, em qual das 3 categorias ele se enquadra
 * quando uma solicitação de esquecimento é executada (Lote A — T045):
 *
 *   1. **anonymize**: campo é substituído por placeholder fixo (preserva linha + FK).
 *   2. **delete**: campo é zerado (NULL) ou arquivo é fisicamente removido.
 *   3. **preserve**: campo permanece intacto por obrigação legal — UI mostra
 *      banner "Dados preservados — retenção até DD/MM/AAAA".
 *
 * O método {@see plan()} retorna o plano para um `patient_id` sem executá-lo —
 * usado em UI para mostrar ao Admin Clínica EXATAMENTE o que vai mudar antes
 * de confirmar (AC-13.2.3 / quickstart cenário 2).
 *
 * **NÃO faz alteração de DB** — execução fica no `ForgettingExecutor` (T045).
 *
 * @see specs/008-finalizacao-mvp/research.md §1 Q26
 */
final class AnonymizationMap
{
    /**
     * Categoria de tratamento por campo.
     */
    public const CATEGORY_ANONYMIZE = 'anonymize';
    public const CATEGORY_DELETE = 'delete';
    public const CATEGORY_PRESERVE = 'preserve';

    /**
     * Mapa campo → categoria + placeholder/reason.
     *
     * Estruturado para que o {@see ForgettingExecutor} consiga iterar e
     * aplicar a transformação correta a cada campo. Adições requerem
     * atualização correspondente no executor (T045) e nos testes (T062, T067).
     *
     * @return array<string, array{category: 'anonymize'|'delete'|'preserve', placeholder?: string|null, reason?: string, retention_days?: int}>
     */
    public static function fieldMap(): array
    {
        return [
            // ─── Anonimizados com placeholder (preservam linha + FK) ───
            'nome' => [
                'category' => self::CATEGORY_ANONYMIZE,
                'placeholder' => 'Paciente Anonimizado #{id}',
            ],
            'cpf' => [
                'category' => self::CATEGORY_ANONYMIZE,
                'placeholder' => '000.000.000-00',
            ],
            'rg' => [
                'category' => self::CATEGORY_ANONYMIZE,
                'placeholder' => null,
            ],
            'telefone' => [
                'category' => self::CATEGORY_ANONYMIZE,
                'placeholder' => '00000000000',
            ],
            'email' => [
                'category' => self::CATEGORY_ANONYMIZE,
                'placeholder' => null,
            ],
            'data_nascimento' => [
                'category' => self::CATEGORY_ANONYMIZE,
                'placeholder' => '1900-01-01',
            ],
            'convenio_carteirinha' => [
                'category' => self::CATEGORY_ANONYMIZE,
                'placeholder' => null,
            ],

            // ─── Deletados fisicamente (storage + coluna nullada) ───
            'foto_url' => [
                'category' => self::CATEGORY_DELETE,
            ],
            'endereco_completo' => [
                'category' => self::CATEGORY_DELETE,
            ],
            'anotacoes_livres' => [
                'category' => self::CATEGORY_DELETE,
            ],
            'mensagens_corpo' => [
                'category' => self::CATEGORY_DELETE,
            ],

            // ─── Preservados por obrigação legal (banner na UI) ───
            'prescricoes_controladas' => [
                'category' => self::CATEGORY_PRESERVE,
                'reason' => 'portaria_344_98',
                'retention_days' => 730, // 2 anos a partir da data de emissão
            ],
            'registros_financeiros' => [
                'category' => self::CATEGORY_PRESERVE,
                'reason' => 'lei_12682_2012',
                'retention_days' => 1825, // 5 anos da data da transação
            ],
            'audit_logs' => [
                'category' => self::CATEGORY_PRESERVE,
                'reason' => 'lgpd_art_16',
                'retention_days' => 365, // 1 ano da data do log
            ],
            'consentimentos' => [
                'category' => self::CATEGORY_PRESERVE,
                'reason' => 'prova_de_conformidade_lgpd',
                'retention_days' => 1825, // 5 anos pós-revogação
            ],
        ];
    }

    /**
     * Retorna campos por categoria. Útil para iteração no executor.
     *
     * @return list<string>
     */
    public static function fieldsByCategory(string $category): array
    {
        return array_keys(array_filter(
            self::fieldMap(),
            static fn (array $config): bool => $config['category'] === $category,
        ));
    }

    /**
     * Constrói o plano de execução para um paciente, gerando os valores
     * concretos de placeholder (ex.: "Paciente Anonimizado #123").
     *
     * @return array{anonymize: array<string, string|null>, delete: list<string>, preserve: list<array{field: string, reason: string, retention_days: int}>}
     */
    public static function plan(int $patientId): array
    {
        $anonymize = [];
        $delete = [];
        $preserve = [];

        foreach (self::fieldMap() as $field => $config) {
            switch ($config['category']) {
                case self::CATEGORY_ANONYMIZE:
                    $placeholder = $config['placeholder'] ?? null;
                    if (is_string($placeholder)) {
                        $placeholder = str_replace('{id}', (string) $patientId, $placeholder);
                    }
                    $anonymize[$field] = $placeholder;
                    break;

                case self::CATEGORY_DELETE:
                    $delete[] = $field;
                    break;

                case self::CATEGORY_PRESERVE:
                    $preserve[] = [
                        'field' => $field,
                        'reason' => $config['reason'] ?? 'unspecified',
                        'retention_days' => $config['retention_days'] ?? 0,
                    ];
                    break;
            }
        }

        return [
            'anonymize' => $anonymize,
            'delete' => $delete,
            'preserve' => $preserve,
        ];
    }
}

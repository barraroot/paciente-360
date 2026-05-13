<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * T083 — migra testes legados de `$this->actingAs(...)` para
 * `\Laravel\Sanctum\Sanctum::actingAs(...)` (Fase 4 — Token Auth Migration).
 *
 * Motivação: após Lote D, todos os endpoints autenticados usam `auth:sanctum`.
 * Tests legados continuam funcionando por coincidência (Sanctum tem fallback
 * `sanctum.guard = ['web']` que aceita user setado na sessão web). Migrar
 * explicita a intenção e habilita fechar esse fallback (`sanctum.guard = []`)
 * sem causar regressão silenciosa.
 *
 * Transformações (APENAS statements standalone — não chained):
 *  1. `$this->actingAs($user);` → `Sanctum::actingAs($user, ['*']);`
 *  2. `$this->actingAs($user, 'web');` → `Sanctum::actingAs($user, ['*'], 'web');`
 *  3. Adiciona `use Laravel\Sanctum\Sanctum;` quando ausente
 *
 * Chained calls são DELIBERADAMENTE preservados:
 *   `$this->actingAs($user)->getJson(...)` — `Sanctum::actingAs` retorna User,
 *   não TestCase; transformar quebraria a chain (User não tem getJson()).
 *   Chains continuam funcionando via fallback `sanctum.guard = ['web']` até
 *   o operador decidir migrá-las manualmente.
 *
 * Idempotente: rodar 2x não altera código já migrado.
 *
 * Modos:
 *   --preview         Lista arquivos + transformações sem alterar nada
 *   --apply           Aplica as transformações in-place
 *   --verify          Após --apply, roda `artisan test --compact`
 *   --only=<path>     Restringe a uma pasta (ex.: tests/Feature/Fase0)
 *
 * Edge cases NÃO tratados automaticamente (relatados para revisão manual):
 *   - `$this->actingAs($user, 'api')` ou outro guard custom — pula com NOTE
 *   - Tests que mexem com session(), cookies(), CSRF explicitamente — pula
 *   - Multi-line actingAs com encadeamento complexo — pula
 *
 * @see specs/004-token-auth-migration/research.md R6
 * @see specs/004-token-auth-migration/tasks.md Phase 10 (T083-T089)
 */
class TestsMigrateActingAsCommand extends Command
{
    protected $signature = 'tests:migrate-actingas-to-sanctum
        {--preview : Lista transformações sem aplicar}
        {--apply : Aplica as transformações in-place}
        {--verify : Após apply, roda a suite full}
        {--only= : Restringe a uma pasta específica}';

    protected $description = 'Migra $this->actingAs(...) → Sanctum::actingAs(...) em tests legados (Fase 4 Lote I).';

    private const DEFAULT_ROOT = 'tests';

    private const FASE4_PATTERN = 'Fase4/';

    public function handle(): int
    {
        $preview = (bool) $this->option('preview');
        $apply = (bool) $this->option('apply');
        $verify = (bool) $this->option('verify');
        $only = $this->option('only');

        if (! $preview && ! $apply) {
            $this->error('Especifique --preview ou --apply.');

            return self::FAILURE;
        }

        $root = $only ?: self::DEFAULT_ROOT;

        if (! is_dir(base_path($root))) {
            $this->error("Diretório não encontrado: {$root}");

            return self::FAILURE;
        }

        $finder = (new Finder)
            ->files()
            ->in(base_path($root))
            ->name('*Test.php')
            ->notPath(self::FASE4_PATTERN);

        $stats = [
            'files_scanned' => 0,
            'files_modified' => 0,
            'replacements' => 0,
            'manual_review' => [],
        ];

        foreach ($finder as $file) {
            $stats['files_scanned']++;
            $path = $file->getRealPath();
            $original = file_get_contents($path);

            [$modified, $count, $warnings] = $this->transform($original);

            if ($count > 0) {
                $stats['files_modified']++;
                $stats['replacements'] += $count;

                $relative = str_replace(base_path().'/', '', $path);

                if ($preview) {
                    $this->line(sprintf('  %s — %d substituição(ões)', $relative, $count));
                }

                if ($apply) {
                    file_put_contents($path, $modified);
                }
            }

            foreach ($warnings as $warning) {
                $stats['manual_review'][] = sprintf(
                    '%s: %s',
                    str_replace(base_path().'/', '', $path),
                    $warning,
                );
            }
        }

        $this->line(str_repeat('─', 60));
        $this->info(sprintf(
            '%s: %d arquivo(s) escaneado(s), %d com transformações, %d substituição(ões) %s.',
            $preview ? 'PREVIEW' : 'APPLY',
            $stats['files_scanned'],
            $stats['files_modified'],
            $stats['replacements'],
            $preview ? 'a aplicar' : 'aplicadas',
        ));

        if (! empty($stats['manual_review'])) {
            $this->newLine();
            $this->warn(sprintf('%d caso(s) requerem revisão manual:', count($stats['manual_review'])));
            foreach ($stats['manual_review'] as $note) {
                $this->line('  • '.$note);
            }
        }

        if ($verify && $apply) {
            $this->newLine();
            $this->info('Rodando `artisan test --compact` para verificar...');
            $exit = $this->call('test', ['--compact' => true]);

            return $exit === 0 ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Aplica as transformações ao conteúdo do arquivo.
     *
     * @return array{0: string, 1: int, 2: list<string>}
     */
    private function transform(string $content): array
    {
        $warnings = [];
        $totalReplacements = 0;

        // Detecta usos de guard custom (não 'web') — não migra, sinaliza.
        if (preg_match_all('/\$this->actingAs\([^)]+,\s*[\'"](?!web[\'"])([^\'"]+)[\'"]\)/', $content, $customGuards)) {
            foreach (array_unique($customGuards[1]) as $guard) {
                $warnings[] = "actingAs com guard custom '{$guard}' — revisar manualmente";
            }
        }

        // Detecta chained calls — NÃO migra, sinaliza para revisão manual.
        // Sanctum::actingAs retorna User, não TestCase — quebraria $this->actingAs($u)->getJson(...).
        if (preg_match_all('/\$this->actingAs\([^)]+\)->/', $content, $chained)) {
            $warnings[] = sprintf(
                'actingAs encadeado (%d ocorrência%s) — separar manualmente em Sanctum::actingAs(...); $this->...',
                count($chained[0]),
                count($chained[0]) > 1 ? 's' : '',
            );
        }

        // Regra 1: $this->actingAs($user);  → Sanctum::actingAs($user, ['*']);
        // Exige `;` (statement standalone) — não casa em chains.
        $pattern1 = '/\$this->actingAs\(\s*(\$[a-zA-Z_][a-zA-Z0-9_]*(?:->\w+)*)\s*\)\s*;/';
        $content = preg_replace_callback(
            $pattern1,
            function (array $m) use (&$totalReplacements): string {
                $totalReplacements++;

                return "Sanctum::actingAs({$m[1]}, ['*']);";
            },
            $content,
        );

        // Regra 2: $this->actingAs($user, 'web');  → Sanctum::actingAs($user, ['*'], 'web');
        $pattern2 = '/\$this->actingAs\(\s*(\$[a-zA-Z_][a-zA-Z0-9_]*(?:->\w+)*)\s*,\s*[\'"]web[\'"]\s*\)\s*;/';
        $content = preg_replace_callback(
            $pattern2,
            function (array $m) use (&$totalReplacements): string {
                $totalReplacements++;

                return "Sanctum::actingAs({$m[1]}, ['*'], 'web');";
            },
            $content,
        );

        // Regra 3: garantir `use Laravel\Sanctum\Sanctum;` quando houve troca.
        if ($totalReplacements > 0 && ! str_contains($content, 'use Laravel\\Sanctum\\Sanctum;')) {
            $content = $this->insertUseStatement($content, 'use Laravel\\Sanctum\\Sanctum;');
        }

        return [$content, $totalReplacements, $warnings];
    }

    /**
     * Insere `use ...;` no bloco de uses do arquivo. Se já houver imports,
     * insere após o último; caso contrário, após o namespace.
     */
    private function insertUseStatement(string $content, string $useLine): string
    {
        if (preg_match_all('/^use [^\n;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastUse = end($matches[0]);
            $insertPos = $lastUse[1] + strlen($lastUse[0]);

            return substr($content, 0, $insertPos)."\n".$useLine.substr($content, $insertPos);
        }

        return preg_replace(
            '/^namespace [^;]+;$/m',
            "$0\n\n".$useLine,
            $content,
            1,
        );
    }
}

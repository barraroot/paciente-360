<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Detecta e resolve emails duplicados cross-tenant (FR-001a / NC-1.a fix).
 *
 * Pré-requisito obrigatório da migration UNIQUE global em users.email.
 * Bloqueia em produção — requer ação manual auditada nesse ambiente.
 */
class UsersDedupeEmailsCrossTenantCommand extends Command
{
    protected $signature = 'users:dedupe-emails-cross-tenant
                            {--check : Apenas detecta duplicatas e retorna exit 1 se houver}
                            {--interactive : Resolve duplicatas interativamente (pergunta para cada)}
                            {--auto : Resolve aplicando regra "manter no tenant mais ativo (last_login_at)"}';

    protected $description = 'Detecta e resolve emails duplicados cross-tenant (FR-001a / NC-1.a fix). Pré-requisito da migration UNIQUE global em users.email.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Bloqueado em produção. Execute em staging ou local. Em prod, ação manual auditada é exigida.');

            return self::FAILURE;
        }

        $mode = match (true) {
            $this->option('check') => 'check',
            $this->option('interactive') => 'interactive',
            $this->option('auto') => 'auto',
            default => null,
        };

        if ($mode === null) {
            $this->error('Especifique modo: --check | --interactive | --auto');

            return self::FAILURE;
        }

        $duplicates = $this->detectDuplicates();

        if ($duplicates->isEmpty()) {
            $this->info('Nenhum email duplicado entre tenants. Migration pode prosseguir.');

            return self::SUCCESS;
        }

        $this->displayDuplicatesTable($duplicates);

        if ($mode === 'check') {
            $this->warn('Para resolver, execute: users:dedupe-emails-cross-tenant --auto (ou --interactive)');

            return self::FAILURE;
        }

        $resolutions = $mode === 'auto'
            ? $this->resolveAutomatically($duplicates)
            : $this->resolveInteractively($duplicates);

        $this->applyResolutions($resolutions);
        $this->info("{$resolutions->count()} duplicatas resolvidas. Migration agora pode aplicar UNIQUE global.");

        return self::SUCCESS;
    }

    /**
     * Retorna emails que aparecem em mais de um tenant (excluindo soft-deleted).
     *
     * @return Collection<int, object{email: string, duplicate_count: int}>
     */
    private function detectDuplicates(): Collection
    {
        return DB::table('users')
            ->select('email', DB::raw('COUNT(*) as duplicate_count'))
            ->whereNull('deleted_at')
            ->groupBy('email')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();
    }

    /**
     * Exibe tabela de duplicatas detectadas no terminal.
     *
     * @param Collection<int, object> $duplicates
     */
    private function displayDuplicatesTable(Collection $duplicates): void
    {
        $this->error("{$duplicates->count()} emails duplicados detectados:");

        $rows = $duplicates->map(function (object $d): array {
            $users = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('email', $d->email)
                ->with('tenant')
                ->get();

            return [
                'email' => $d->email,
                'tenants' => $users->map(fn (User $u) => $u->tenant?->slug ?? "tenant_{$u->tenant_id}")->implode(', '),
                'count' => $d->duplicate_count,
                'last_logins' => $users->map(fn (User $u) => ($u->tenant?->slug ?? "t{$u->tenant_id}").': '.($u->last_login_at?->format('Y-m-d') ?? 'never'))->implode('; '),
            ];
        })->toArray();

        $this->table(['Email', 'Tenants', 'Count', 'Last Logins'], $rows);
    }

    /**
     * Resolve automaticamente: mantém email no tenant com last_login_at mais recente.
     * Em empate (ambos null), mantém no tenant com ID mais baixo (criado primeiro).
     *
     * @param Collection<int, object> $duplicates
     * @return Collection<int, array{email: string, keep_user_id: int, rename: array<int, array{user_id: int, new_email: string}>}>
     */
    private function resolveAutomatically(Collection $duplicates): Collection
    {
        return $duplicates->map(function (object $d): array {
            $users = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('email', $d->email)
                ->with('tenant')
                ->get()
                ->sortByDesc(fn (User $u): int => $u->last_login_at?->timestamp ?? 0)
                ->values();

            $keep = $users->first();
            $rename = $users->slice(1);

            return [
                'email' => $d->email,
                'keep_user_id' => $keep->id,
                'rename' => $rename->map(fn (User $u): array => [
                    'user_id' => $u->id,
                    'new_email' => "{$d->email}.tenant-".($u->tenant?->slug ?? "t{$u->tenant_id}"),
                ])->values()->toArray(),
            ];
        });
    }

    /**
     * Resolve interativamente perguntando qual tenant mantém o email original.
     *
     * @param Collection<int, object> $duplicates
     * @return Collection<int, array{email: string, keep_user_id: int, rename: array<int, array{user_id: int, new_email: string}>}>
     */
    private function resolveInteractively(Collection $duplicates): Collection
    {
        return $duplicates->map(function (object $d): array {
            $users = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('email', $d->email)
                ->with('tenant')
                ->get();

            $choices = $users->map(
                fn (User $u): string => ($u->tenant?->slug ?? "tenant_{$u->tenant_id}").' (last_login: '.($u->last_login_at?->format('Y-m-d') ?? 'never').')'
            )->toArray();

            $keepChoice = $this->choice("Qual tenant mantém o email '{$d->email}'?", $choices);
            $keepIdx = array_search($keepChoice, $choices);
            $keepUser = $users[$keepIdx];
            $rename = $users->filter(fn (User $u): bool => $u->id !== $keepUser->id);

            return [
                'email' => $d->email,
                'keep_user_id' => $keepUser->id,
                'rename' => $rename->map(fn (User $u): array => [
                    'user_id' => $u->id,
                    'new_email' => "{$d->email}.tenant-".($u->tenant?->slug ?? "t{$u->tenant_id}"),
                ])->values()->toArray(),
            ];
        });
    }

    /**
     * Aplica as resoluções em transação, gerando audit log por cada renomeação.
     *
     * @param Collection<int, array{email: string, keep_user_id: int, rename: array<int, array{user_id: int, new_email: string}>}> $resolutions
     */
    private function applyResolutions(Collection $resolutions): void
    {
        DB::transaction(function () use ($resolutions): void {
            foreach ($resolutions as $resolution) {
                foreach ($resolution['rename'] as $action) {
                    DB::table('users')
                        ->where('id', $action['user_id'])
                        ->update(['email' => $action['new_email'], 'updated_at' => now()]);

                    Log::info('user.email_renamed_for_global_uniqueness', [
                        'user_id' => $action['user_id'],
                        'old_email' => $resolution['email'],
                        'new_email' => $action['new_email'],
                    ]);
                }
            }
        });
    }
}

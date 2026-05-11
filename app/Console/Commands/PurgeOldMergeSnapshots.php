<?php

namespace App\Console\Commands;

use App\Models\MesclagemPaciente;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:purge-old-merge-snapshots')]
#[Description('T261 — Purga snapshots de mesclagens antigas (> 30 dias de reversibilidade)')]
class PurgeOldMergeSnapshots extends Command
{
    public function handle(): int
    {
        $cutoffDate = now()->subDays(30);

        $updated = MesclagemPaciente::query()
            ->where('reversivel_ate', '<', $cutoffDate)
            ->whereNotNull('snapshot_pre_merge')
            ->update(['snapshot_pre_merge' => '{}']);

        $this->info("Purged {$updated} old merge snapshots.");

        return Command::SUCCESS;
    }
}

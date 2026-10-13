<?php

namespace App\Console\Commands;

use App\Support\AutoIncrement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResyncReportIds extends Command
{
    protected $signature = 'reports:resync-ids {--renumber : Also close gaps so ids run 1..N in their current order}';

    protected $description = 'Reset the reports id counter to MAX(id)+1 (and optionally renumber reports sequentially)';

    public function handle(): int
    {
        if ($this->option('renumber')) {
            $this->renumber();
        }

        AutoIncrement::sync('reports');

        $this->info('Next report id will be '.((int) DB::table('reports')->max('id') + 1).'.');

        return self::SUCCESS;
    }

    private function renumber(): void
    {
        $moved = 0;
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use (&$moved) {
                $n = 0;
                // new id is always <= old id and ids are visited in ascending order, so no collisions.
                foreach (DB::table('reports')->orderBy('id')->pluck('id') as $old) {
                    $n++;
                    if ($old === $n) {
                        continue;
                    }
                    DB::table('report_attachments')->where('report_id', $old)->update(['report_id' => $n]);
                    DB::table('reports')->where('id', $old)->update(['id' => $n]);
                    $this->line("  #{$old} -> #{$n}");
                    $moved++;
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info($moved ? "Renumbered {$moved} report(s)." : 'Ids were already sequential.');
    }
}

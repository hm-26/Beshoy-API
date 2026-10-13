<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves a report (and its attachments) to a different number.
 * Callers must have checked the target number is free.
 */
class ReportNumber
{
    public static function move(int $from, int $to): void
    {
        if ($from === $to) {
            return;
        }

        // Foreign keys are switched off OUTSIDE the transaction (SQLite ignores the pragma inside one).
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($from, $to) {
                DB::table('report_attachments')->where('report_id', $from)->update(['report_id' => $to]);
                DB::table('reports')->where('id', $from)->update(['id' => $to]);
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // The counter must follow the new max id (or fall back if the old, higher number was freed).
        AutoIncrement::sync('reports');
    }
}

<?php

namespace App\Console\Commands;

use App\Support\DataVault;
use Illuminate\Console\Command;

class EncryptData extends Command
{
    protected $signature = 'data:encrypt';

    protected $description = 'Encrypt every table with personal / report data and the uploaded files that were stored before encryption at rest (safe to re-run)';

    public function handle(): int
    {
        foreach (DataVault::encryptTables() as $table => $rows) {
            $this->info("{$table}: {$rows} row(s) encrypted");
        }
        [$done, $failed] = DataVault::encryptAttachments();
        $this->info("Files encrypted: {$done}".($failed ? " ({$failed} failed, see the log)" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

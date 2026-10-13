<?php

namespace App\Console\Commands;

use App\Support\DataVault;
use Illuminate\Console\Command;

class EncryptData extends Command
{
    protected $signature = 'data:encrypt';

    protected $description = 'Encrypt contact messages and uploaded files that were stored before encryption at rest (safe to re-run)';

    public function handle(): int
    {
        $this->info('Messages encrypted: '.DataVault::encryptMessages());
        [$done, $failed] = DataVault::encryptAttachments();
        $this->info("Files encrypted: {$done}".($failed ? " ({$failed} failed, see the log)" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

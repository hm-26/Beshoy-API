<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** One-off conversion of data stored before encryption at rest was switched on. Safe to run again and again. */
final class DataVault
{
    public const MESSAGE_COLUMNS = ['name', 'email', 'phone', 'subject', 'message'];

    /** Encrypts every plaintext contact-message value. @return int number of rows changed */
    public static function encryptMessages(): int
    {
        $changed = 0;
        DB::table('contact_messages')->orderBy('id')->chunkById(200, function ($rows) use (&$changed) {
            foreach ($rows as $row) {
                $update = [];
                foreach (self::MESSAGE_COLUMNS as $col) {
                    $v = $row->{$col};
                    if ($v === null || $v === '') {
                        continue;
                    }
                    try {
                        Crypt::decryptString((string) $v);
                    } catch (DecryptException) {
                        $update[$col] = Crypt::encryptString((string) $v);
                    }
                }
                if ($update) {
                    DB::table('contact_messages')->where('id', $row->id)->update($update);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /**
     * Encrypts every stored attachment that is still plaintext.
     *
     * @return array{0:int,1:int} [converted, failed]
     */
    public static function encryptAttachments(): array
    {
        $disk = Storage::disk(\App\Models\ReportAttachment::DISK);
        $done = 0;
        $failed = 0;
        DB::table('report_attachments')->orderBy('id')->chunkById(50, function ($rows) use ($disk, &$done, &$failed) {
            foreach ($rows as $row) {
                if (! $disk->exists($row->path)) {
                    continue;
                }
                try {
                    if (FileVault::encryptInPlace($disk->path($row->path))) {
                        $done++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    report($e);
                }
            }
        });

        return [$done, $failed];
    }
}

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
    /** table => encrypted columns (kept in step with the model casts). */
    public const TABLES = [
        'contact_messages' => ['name', 'email', 'phone', 'subject', 'message'],
        'reports' => [
            'platform', 'program_type', 'bounty', 'asset', 'weakness', 'title', 'summary', 'steps_to_reproduce',
            'supporting_references', 'endpoint', 'impact', 'recommendation', 'request', 'response', 'ar',
        ],
        'profiles' => ['name', 'role', 'bio', 'location', 'skills', 'contact', 'ar'],
        'report_attachments' => ['original_name', 'caption', 'caption_ar'],
        'profile_avatars' => ['data'],
    ];

    /** Encrypts every plaintext value of the given columns. @return int number of rows changed */
    public static function encryptColumns(string $table, array $columns): int
    {
        $changed = 0;
        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns, &$changed) {
            foreach ($rows as $row) {
                $update = [];
                foreach ($columns as $col) {
                    $v = $row->{$col} ?? null;
                    if ($v === null || $v === '') {
                        continue;
                    }
                    if (! is_string($v)) {
                        $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                    }
                    try {
                        Crypt::decryptString($v);
                    } catch (DecryptException) {
                        $update[$col] = Crypt::encryptString($v);
                    }
                }
                if ($update) {
                    DB::table($table)->where('id', $row->id)->update($update);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /** Encrypts every table listed in self::TABLES. @return array<string,int> rows changed per table */
    public static function encryptTables(): array
    {
        $out = [];
        foreach (self::TABLES as $table => $columns) {
            $out[$table] = self::encryptColumns($table, $columns);
        }

        return $out;
    }

    /** Kept for the first migration. */
    public static function encryptMessages(): int
    {
        return self::encryptColumns('contact_messages', self::TABLES['contact_messages']);
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

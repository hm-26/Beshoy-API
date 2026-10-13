<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pulls a table's auto-increment counter back to MAX(id) + 1, so ids freed by deleting the
 * newest rows are handed out again (delete #8 and #9 -> the next row is #8, not #10).
 *
 * MySQL's ALTER TABLE commits implicitly, so call this AFTER the delete, never inside a transaction.
 */
class AutoIncrement
{
    public static function sync(string $table): void
    {
        $next = (int) DB::table($table)->max('id') + 1;

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}"),
            'pgsql' => DB::statement('SELECT setval(pg_get_serial_sequence(?, ?), ?, false)', [$table, 'id', $next]),
            'sqlite' => DB::statement('UPDATE sqlite_sequence SET seq = ? WHERE name = ?', [$next - 1, $table]),
            default => null,
        };
    }
}

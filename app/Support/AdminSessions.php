<?php

namespace App\Support;

use App\Models\User;

/**
 * Which admin sessions ("devices") count as open, and how many the admin allows at once.
 *
 * Each browser has one Sanctum token, named after its device cookie (see AuthController). A token counts as an
 * open session while it has not expired AND was used recently: the admin panel pings the API every few minutes,
 * so a tab that is open stays active, while a closed tab / dead phone stops counting after IDLE_MINUTES.
 */
class AdminSessions
{
    public const IDLE_MINUTES = 10;
    public const DEFAULT_DEVICES = 4;           // used until the admin picks a number in Admin > Settings
    public const MIN_DEVICES = 1;
    public const MAX_DEVICES = 1_000_000_000;   // "unlimited" is stored as 0 and has its own switch in the settings

    /**
     * How many devices may be signed in at once: a number >= 1, or null for "no limit".
     * Falls back to DEFAULT_DEVICES until the admin chooses (or the migration has been run).
     */
    public static function limit(User $user): ?int
    {
        $value = $user->max_devices;
        if ($value === null) {
            return self::DEFAULT_DEVICES;
        }

        return (int) $value === 0 ? null : max(self::MIN_DEVICES, min(self::MAX_DEVICES, (int) $value));
    }

    /** Query of the user's tokens that are currently open sessions. */
    public static function active(User $user)
    {
        $since = now()->subMinutes(self::IDLE_MINUTES);

        return $user->tokens()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q
                ->where('last_used_at', '>', $since)
                ->orWhere(fn ($q2) => $q2->whereNull('last_used_at')->where('created_at', '>', $since)));
    }
}

<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a column at rest (AES-256-CBC + HMAC through Laravel's Crypt, i.e. APP_KEY / APP_PREVIOUS_KEYS).
 *
 * Reading tolerates rows that were stored before encryption was switched on (they come back as plain text), so
 * deploying this never breaks existing data. `php artisan data:encrypt` (and the migration that ships with it)
 * converts those rows.
 */
class SafeEncrypted implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return $value; // legacy plaintext row
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value === null || $value === '' ? $value : Crypt::encryptString((string) $value);
    }
}

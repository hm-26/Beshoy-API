<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Like SafeEncrypted, for array / object columns: the JSON text is encrypted as one block (so even the key names are
 * hidden), and read back as an array. A row stored as plain JSON before encryption was switched on is still readable.
 */
class SafeEncryptedJson implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $json = Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            $json = (string) $value; // legacy plain JSON row
        }

        return json_decode($json, true);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}

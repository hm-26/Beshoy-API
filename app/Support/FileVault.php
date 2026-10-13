<?php

namespace App\Support;

use RuntimeException;

/**
 * Encryption at rest for uploaded files.
 *
 * Every stored attachment is encrypted with AES-256-GCM, so a copy of the disk / volume / backup is useless without the
 * server's APP_KEY. The file is cut into 64 KiB chunks that are encrypted independently: memory use stays flat (a 100 MB
 * video never sits in RAM) and HTTP Range requests (video seeking) work without decrypting everything before the byte.
 *
 * Layout:  header (32 bytes) | chunk 0 | chunk 1 | ...        chunk = ciphertext (<= 64 KiB) + GCM tag (16 bytes)
 *   header = "GLXVLT01" (8) | random per-file salt (16) | plaintext size, 64-bit big-endian (8)
 *   key    = HKDF-SHA256(master key, salt)        -> every file has its own key
 *   nonce  = 4 zero bytes | chunk index (8)       -> unique per chunk (the key is unique per file)
 *   AAD    = header | chunk index                 -> a chunk cannot be moved, repeated, dropped or re-used in another
 *                                                    file, and the stored size cannot be changed, without detection.
 * Anything that fails authentication is refused: data is never served half-checked.
 *
 * Losing APP_KEY means losing the files. When rotating the key, put the old one in APP_PREVIOUS_KEYS.
 */
final class FileVault
{
    public const MAGIC = 'GLXVLT01';
    public const CHUNK = 65536;
    private const HEADER = 32;
    private const TAG = 16;

    /** @return list<string> 32-byte master keys: the current APP_KEY first, then APP_PREVIOUS_KEYS (key rotation). */
    public static function configuredKeys(): array
    {
        $raw = array_merge([(string) config('app.key')], (array) config('app.previous_keys', []));
        $keys = [];
        foreach ($raw as $k) {
            $k = (string) $k;
            if ($k === '') {
                continue;
            }
            $bytes = str_starts_with($k, 'base64:') ? base64_decode(substr($k, 7), true) : $k;
            if (is_string($bytes) && $bytes !== '') {
                $keys[] = hash_hkdf('sha256', $bytes, 32, 'glitch-file-vault-v1');
            }
        }
        if ($keys === []) {
            throw new RuntimeException('APP_KEY is not set, files cannot be encrypted.');
        }

        return $keys;
    }

    public static function isEncrypted(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if (! $h) {
            return false;
        }
        $magic = fread($h, strlen(self::MAGIC));
        fclose($h);

        return $magic === self::MAGIC;
    }

    /** Encrypts $src into $dst (created / overwritten). Returns the plaintext size. */
    public static function encrypt(string $src, string $dst, ?array $keys = null): int
    {
        $key = ($keys ?? self::configuredKeys())[0];
        $in = @fopen($src, 'rb');
        if (! $in) {
            throw new RuntimeException('Cannot read the file to encrypt.');
        }
        $size = (int) filesize($src);
        $salt = random_bytes(16);
        $header = self::MAGIC.$salt.pack('J', $size);
        $fileKey = hash_hkdf('sha256', $key, 32, 'file', $salt);

        $out = @fopen($dst, 'wb');
        if (! $out) {
            fclose($in);
            throw new RuntimeException('Cannot write the encrypted file.');
        }

        try {
            fwrite($out, $header);
            $index = 0;
            $written = 0;
            while ($written < $size) {
                $plain = self::readFully($in, min(self::CHUNK, $size - $written));
                if ($plain === '') {
                    throw new RuntimeException('The file changed while it was being encrypted.');
                }
                $tag = '';
                $cipher = openssl_encrypt($plain, 'aes-256-gcm', $fileKey, OPENSSL_RAW_DATA, self::nonce($index), $tag, $header.pack('J', $index), self::TAG);
                if ($cipher === false || strlen($tag) !== self::TAG) {
                    throw new RuntimeException('Encryption failed.');
                }
                fwrite($out, $cipher.$tag);
                $written += strlen($plain);
                $index++;
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return $size;
    }

    /** Size of the decrypted file, or null when this is not a (complete) vault file. */
    public static function plainSize(string $path): ?int
    {
        $h = @fopen($path, 'rb');
        if (! $h) {
            return null;
        }
        $header = self::readFully($h, self::HEADER);
        fclose($h);
        if (strlen($header) !== self::HEADER || ! str_starts_with($header, self::MAGIC)) {
            return null;
        }
        $size = unpack('J', substr($header, 24, 8))[1];
        if ($size < 0) {
            return null;
        }
        $chunks = intdiv($size + self::CHUNK - 1, self::CHUNK);

        // A truncated or padded file is refused up front. (PHP caches file sizes per path: ask the disk, not the cache.)
        clearstatcache(true, $path);

        return filesize($path) === self::HEADER + $size + $chunks * self::TAG ? $size : null;
    }

    /**
     * Streams plaintext bytes $from..$to (inclusive). Throws before yielding anything if the first chunk it needs does not
     * authenticate (wrong key / tampered file), so a caller can check with verify() and answer cleanly.
     *
     * @return \Generator<string>
     */
    public static function read(string $path, int $from, int $to, ?array $keys = null): \Generator
    {
        $size = self::plainSize($path);
        if ($size === null) {
            throw new RuntimeException('Not a valid encrypted file.');
        }
        if ($size === 0 || $from > $to || $from >= $size) {
            return;
        }
        $to = min($to, $size - 1);

        $h = fopen($path, 'rb');
        try {
            $header = self::readFully($h, self::HEADER);
            $salt = substr($header, 8, 16);
            $fileKey = self::fileKeyFor($h, $header, $salt, $size, $keys ?? self::configuredKeys());

            for ($i = intdiv($from, self::CHUNK), $last = intdiv($to, self::CHUNK); $i <= $last; $i++) {
                $plain = self::decryptChunk($h, $header, $fileKey, $i, $size);
                $chunkStart = $i * self::CHUNK;
                $a = max(0, $from - $chunkStart);
                $b = min(strlen($plain), $to - $chunkStart + 1);
                if ($b > $a) {
                    yield substr($plain, $a, $b - $a);
                }
            }
        } finally {
            fclose($h);
        }
    }

    /** True if the file can be decrypted with one of the keys (checks the first and the last chunk). */
    public static function verify(string $path, ?array $keys = null): bool
    {
        $size = self::plainSize($path);
        if ($size === null) {
            return false;
        }
        if ($size === 0) {
            return true;
        }
        try {
            foreach (self::read($path, 0, 0, $keys) as $_) {
                break;
            }
            foreach (self::read($path, $size - 1, $size - 1, $keys) as $_) {
                break;
            }
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    /** Decrypts the whole file into $dst (used to scan stored PDFs, and to move a file back out). */
    public static function decryptTo(string $path, string $dst, ?array $keys = null): void
    {
        $size = self::plainSize($path);
        if ($size === null) {
            throw new RuntimeException('Not a valid encrypted file.');
        }
        $out = fopen($dst, 'wb');
        try {
            foreach (self::read($path, 0, max(0, $size - 1), $keys) as $chunk) {
                fwrite($out, $chunk);
            }
        } finally {
            fclose($out);
        }
    }

    /** Encrypts a stored plaintext file in place (temp file + rename, so a crash never leaves half a file). */
    public static function encryptInPlace(string $path, ?array $keys = null): bool
    {
        if (! is_file($path) || self::isEncrypted($path)) {
            return false;
        }
        $tmp = $path.'.enc.'.bin2hex(random_bytes(4));
        try {
            self::encrypt($path, $tmp, $keys);
            if (! self::verify($tmp, $keys) || ! @rename($tmp, $path)) {
                throw new RuntimeException('Verification of the encrypted copy failed.');
            }
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;
        }

        return true;
    }

    private static function nonce(int $index): string
    {
        return "\0\0\0\0".pack('J', $index);
    }

    private static function readFully($h, int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $part = fread($h, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }

        return $data;
    }

    /** Finds which master key opens this file by authenticating chunk 0. */
    private static function fileKeyFor($h, string $header, string $salt, int $size, array $keys): string
    {
        foreach ($keys as $master) {
            $fileKey = hash_hkdf('sha256', $master, 32, 'file', $salt);
            try {
                self::decryptChunk($h, $header, $fileKey, 0, $size);

                return $fileKey;
            } catch (RuntimeException) {
                // try the next (previous) key
            }
        }

        throw new RuntimeException('The file cannot be decrypted (wrong key or modified file).');
    }

    private static function decryptChunk($h, string $header, string $fileKey, int $index, int $size): string
    {
        $plainLen = min(self::CHUNK, $size - $index * self::CHUNK);
        fseek($h, self::HEADER + $index * (self::CHUNK + self::TAG));
        $blob = self::readFully($h, $plainLen + self::TAG);
        if (strlen($blob) !== $plainLen + self::TAG) {
            throw new RuntimeException('The encrypted file is truncated.');
        }
        $plain = openssl_decrypt(substr($blob, 0, $plainLen), 'aes-256-gcm', $fileKey, OPENSSL_RAW_DATA, self::nonce($index), substr($blob, $plainLen), $header.pack('J', $index));
        if ($plain === false) {
            throw new RuntimeException('The file cannot be decrypted (wrong key or modified file).');
        }

        return $plain;
    }
}

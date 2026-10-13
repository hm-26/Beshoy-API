<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Application-level encryption of sensitive request bodies (the admin login, the password change), on top of HTTPS.
 *
 * Why: a password sent as readable JSON is visible to everything that terminates TLS or logs bodies on the way
 * (CDN / reverse proxy / platform log, a corporate TLS-inspecting proxy, a browser extension). Here the browser
 * encrypts it for ONE specific request, so those hops only ever see ciphertext.
 *
 *   1. The browser asks for a challenge. The server makes a fresh ECDH P-256 key pair, keeps the private half in the
 *      cache for 90 seconds, and returns { id, key = public half }.
 *   2. The browser makes its own throw-away key pair, derives a shared secret with the server key (ECDH), turns it
 *      into an AES-256 key (HKDF-SHA256, salt = id), and encrypts the JSON body with AES-256-GCM (AAD = id).
 *   3. The server fetches the private half by id AND DELETES IT (single use), derives the same key and decrypts.
 *
 * Every challenge works once only, so a captured request cannot be replayed; the keys are never reused, so a later
 * leak of any secret cannot decrypt old traffic (forward secrecy). Wire format (all base64url):
 *   { id, key: client public key (65-byte uncompressed point), iv: 12 bytes, data: ciphertext || 16-byte tag }
 */
final class SealedPayload
{
    public const TTL = 90;
    public const INFO = 'glx-sealed-v1';

    // ASN.1 header of a P-256 SubjectPublicKeyInfo; the 65-byte public point follows it.
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** @return array{id:string,key:string} */
    public static function challenge(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false || ! openssl_pkey_export($key, $pem)) {
            throw new \RuntimeException('Could not create a key pair.');
        }
        $ec = openssl_pkey_get_details($key)['ec'];
        $point = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);

        $id = bin2hex(random_bytes(16));
        Cache::put('sealed:'.$id, $pem, self::TTL);

        return ['id' => $id, 'key' => self::b64u($point)];
    }

    /**
     * Decrypts a sealed body. Returns the decoded JSON object, or null for anything wrong (unknown / used / expired
     * challenge, tampering, bad input) — the caller must not say which.
     */
    public static function open(mixed $envelope): ?array
    {
        if (! is_array($envelope)) {
            return null;
        }
        foreach (['id', 'key', 'iv', 'data'] as $field) {
            if (! isset($envelope[$field]) || ! is_string($envelope[$field]) || strlen($envelope[$field]) > 8192) {
                return null;
            }
        }
        if (! preg_match('/^[a-f0-9]{32}$/', $envelope['id'])) {
            return null;
        }

        // pull() = read and delete: the challenge can never be used twice, whatever happens next.
        $pem = Cache::pull('sealed:'.$envelope['id']);
        if (! is_string($pem)) {
            return null;
        }

        $point = self::unb64u($envelope['key']);
        $iv = self::unb64u($envelope['iv']);
        $data = self::unb64u($envelope['data']);
        if ($point === null || strlen($point) !== 65 || $point[0] !== "\x04" || $iv === null || strlen($iv) !== 12 || $data === null || strlen($data) < 17) {
            return null;
        }

        $der = hex2bin(self::SPKI_PREFIX).$point;
        $clientPem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";

        $secret = @openssl_pkey_derive($clientPem, $pem, 32);
        if (! is_string($secret) || $secret === '') {
            return null;
        }
        $aesKey = hash_hkdf('sha256', $secret, 32, self::INFO, $envelope['id']);

        $plain = openssl_decrypt(substr($data, 0, -16), 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, substr($data, -16), $envelope['id']);
        if ($plain === false) {
            return null;
        }
        $json = json_decode($plain, true);

        return is_array($json) ? $json : null;
    }

    private static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64u(string $text): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]*$/', $text)) {
            return null;
        }
        $raw = base64_decode(strtr($text, '-_', '+/'), true);

        return $raw === false ? null : $raw;
    }
}

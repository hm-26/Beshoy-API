<?php

namespace Tests\Unit;

use App\Support\FileVault;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileVaultTest extends TestCase
{
    private string $dir;

    private array $key;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/vault-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->key = [hash_hkdf('sha256', random_bytes(32), 32, 'glitch-file-vault-v1')];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function encrypted(string $plain): string
    {
        file_put_contents("{$this->dir}/p", $plain);
        FileVault::encrypt("{$this->dir}/p", "{$this->dir}/e", $this->key);

        return "{$this->dir}/e";
    }

    private function readAll(string $path, int $from, int $to): string
    {
        $out = '';
        foreach (FileVault::read($path, $from, $to, $this->key) as $c) {
            $out .= $c;
        }

        return $out;
    }

    public function test_roundtrip_for_sizes_around_chunk_boundaries(): void
    {
        foreach ([0, 1, 65535, 65536, 65537, 200001] as $n) {
            $plain = $n ? random_bytes($n) : '';
            $path = $this->encrypted($plain);
            $this->assertSame($n, FileVault::plainSize($path));
            $this->assertSame($plain, $this->readAll($path, 0, max(0, $n - 1)));
        }
    }

    public function test_range_reads_match_the_plaintext(): void
    {
        $plain = random_bytes(200001);
        $path = $this->encrypted($plain);
        foreach ([[0, 0], [65535, 65536], [70000, 199999], [200000, 200000], [10, 150000]] as [$a, $b]) {
            $this->assertSame(substr($plain, $a, $b - $a + 1), $this->readAll($path, $a, $b));
        }
    }

    public function test_ciphertext_does_not_contain_the_plaintext(): void
    {
        $plain = str_repeat('SECRET-REPORT-', 100);
        $this->assertStringNotContainsString('SECRET-REPORT', (string) file_get_contents($this->encrypted($plain)));
    }

    public function test_wrong_key_and_tampering_are_refused(): void
    {
        $path = $this->encrypted(random_bytes(200000));
        $other = [hash_hkdf('sha256', random_bytes(32), 32, 'glitch-file-vault-v1')];
        $this->assertFalse(FileVault::verify($path, $other));
        $this->assertTrue(FileVault::verify($path, [$other[0], $this->key[0]])); // key rotation

        $raw = (string) file_get_contents($path);
        $flipped = $raw;
        $flipped[100000] = chr(ord($flipped[100000]) ^ 1);
        file_put_contents($path, $flipped);
        $this->expectException(RuntimeException::class);
        $this->readAll($path, 0, 199999);
    }

    public function test_truncated_file_is_refused(): void
    {
        $path = $this->encrypted(random_bytes(100000));
        file_put_contents($path, substr((string) file_get_contents($path), 0, -20));
        $this->assertNull(FileVault::plainSize($path));
    }

    public function test_encrypt_in_place_is_idempotent(): void
    {
        $plain = random_bytes(150000);
        file_put_contents("{$this->dir}/q", $plain);
        $this->assertTrue(FileVault::encryptInPlace("{$this->dir}/q", $this->key));
        $this->assertFalse(FileVault::encryptInPlace("{$this->dir}/q", $this->key));
        $this->assertSame($plain, $this->readAll("{$this->dir}/q", 0, 149999));
    }
}

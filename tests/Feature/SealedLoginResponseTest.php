<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SealedLoginResponseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'a-long-password-123']);
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_me_is_refused_without_the_seal_handshake(): void
    {
        config(['auth.seal_public_api' => true]);
        Sanctum::actingAs($this->admin(), ['admin']);

        $this->getJson('/api/auth/me')->assertStatus(400)->assertJsonPath('code', 'sealed_required');
    }

    public function test_me_is_returned_as_ciphertext_only(): void
    {
        config(['auth.seal_public_api' => true]);
        Sanctum::actingAs($this->admin(), ['admin']);

        $pair = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $ec = openssl_pkey_get_details($pair)['ec'];
        $point = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
        $key = rtrim(strtr(base64_encode($point), '+/', '-_'), '=');

        $res = $this->getJson('/api/auth/me', ['X-Seal-Key' => $key, 'X-Seal-Nonce' => 'abcdefghijklmnop1234'])->assertOk();

        $this->assertArrayHasKey('sealed', $res->json());
        $this->assertStringNotContainsString('admin@example.com', $res->getContent());
        $this->assertStringNotContainsString('isAdmin', $res->getContent());
    }

    public function test_login_endpoint_still_looks_like_a_404_without_the_gate(): void
    {
        $this->postJson('/api/auth/login', [])->assertNotFound();
    }
}

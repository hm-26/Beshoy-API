<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EncryptionAtRestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.require_sealed' => false]); // admin / contact writes are sealed in production; these tests post plain JSON
        config(['auth.seal_public_api' => false]); // these tests read the plain JSON; sealing has its own test below
    }

    public function test_report_text_is_encrypted_in_the_database_but_served_normally(): void
    {
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'a-long-password-123']);
        $user->is_admin = true;
        $user->save();
        Sanctum::actingAs($user, ['admin']);

        $this->postJson('/api/admin/reports', [
            'title' => 'Unique-Title-XYZ', 'summary' => 'Unique-Summary-XYZ', 'published' => true,
            'request' => ['language' => 'Bash', 'code' => 'curl unique-code-xyz'],
        ])->assertCreated();

        $raw = json_encode(DB::table('reports')->first());
        foreach (['Unique-Title-XYZ', 'Unique-Summary-XYZ', 'unique-code-xyz'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }

        $this->getJson('/api/reports/'.Report::first()->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Unique-Title-XYZ')
            ->assertJsonPath('data.request.code', 'curl unique-code-xyz');
    }

    public function test_profile_is_encrypted_in_the_database_but_served_normally(): void
    {
        Profile::current()->update(['name' => 'Unique-Name-XYZ', 'skills' => ['unique-skill-xyz'], 'contact' => ['email' => 'unique@example.com']]);

        $raw = json_encode(DB::table('profiles')->first());
        foreach (['Unique-Name-XYZ', 'unique-skill-xyz', 'unique@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }

        $this->getJson('/api/profile')->assertOk()
            ->assertJsonPath('data.name', 'Unique-Name-XYZ')
            ->assertJsonPath('data.skills.0', 'unique-skill-xyz');
    }

    public function test_rows_stored_before_encryption_are_still_readable(): void
    {
        DB::table('profiles')->insert(['name' => 'Plain Name', 'skills' => '["a","b"]', 'created_at' => now(), 'updated_at' => now()]);

        $p = Profile::first();
        $this->assertSame('Plain Name', $p->name);
        $this->assertSame(['a', 'b'], $p->skills);
    }

    public function test_public_api_refuses_a_request_that_does_not_do_the_seal_handshake(): void
    {
        config(['auth.seal_public_api' => true]);

        $this->getJson('/api/profile')->assertStatus(400)->assertJsonPath('code', 'sealed_required');
        $this->getJson('/api/reports')->assertStatus(400);
    }
}

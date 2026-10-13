<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportCustomNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.require_sealed' => false, 'auth.seal_public_api' => false]);
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'a-long-password-123']);
        $user->is_admin = true;
        $user->save();
        Sanctum::actingAs($user, ['admin']);
    }

    private function payload(array $extra = []): array
    {
        return ['title' => 'T', 'summary' => 'S'] + $extra;
    }

    public function test_blank_number_keeps_auto_numbering(): void
    {
        $this->postJson('/api/admin/reports', $this->payload(['number' => '']))->assertCreated()->assertJsonPath('data.id', 1);
        $this->postJson('/api/admin/reports', $this->payload())->assertCreated()->assertJsonPath('data.id', 2);
    }

    public function test_custom_number_is_used_and_auto_continues_after_it(): void
    {
        $this->postJson('/api/admin/reports', $this->payload(['number' => 50]))->assertCreated()->assertJsonPath('data.id', 50);
        $this->postJson('/api/admin/reports', $this->payload())->assertCreated()->assertJsonPath('data.id', 51);
    }

    public function test_taken_or_invalid_number_is_rejected(): void
    {
        $this->postJson('/api/admin/reports', $this->payload(['number' => 7]))->assertCreated();
        $this->postJson('/api/admin/reports', $this->payload(['number' => 7]))->assertUnprocessable();
        $this->postJson('/api/admin/reports', $this->payload(['number' => 0]))->assertUnprocessable();
        $this->postJson('/api/admin/reports', $this->payload(['number' => 'abc']))->assertUnprocessable();
    }

    public function test_update_can_renumber_and_blank_or_same_keeps_it(): void
    {
        $r = Report::create(['title' => 'T', 'summary' => 'S']);
        $r->attachments()->create(['path' => 'x', 'original_name' => 'a.txt', 'extension' => 'txt', 'mime' => 'text/plain', 'kind' => 'file', 'size' => 1]);

        $this->putJson("/api/admin/reports/{$r->id}", ['number' => $r->id])->assertOk()->assertJsonPath('data.id', $r->id);
        $this->putJson("/api/admin/reports/{$r->id}", ['number' => null])->assertOk()->assertJsonPath('data.id', $r->id);

        $this->putJson("/api/admin/reports/{$r->id}", ['number' => 42, 'title' => 'New'])
            ->assertOk()->assertJsonPath('data.id', 42)->assertJsonPath('data.title', 'New');

        $this->assertNull(Report::find($r->id));
        $this->assertSame(1, Report::findOrFail(42)->attachments()->count());

        // taken by another report
        $other = Report::create(['title' => 'O', 'summary' => 'S']);
        $this->putJson("/api/admin/reports/{$other->id}", ['number' => 42])->assertUnprocessable();
    }
}

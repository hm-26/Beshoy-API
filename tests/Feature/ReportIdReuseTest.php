<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportIdReuseTest extends TestCase
{
    use RefreshDatabase;

    private function make(): Report
    {
        return Report::create(['title' => 'T', 'summary' => 'S']);
    }

    public function test_ids_of_deleted_newest_reports_are_reused(): void
    {
        config(['auth.require_sealed' => false, 'auth.seal_public_api' => false]);
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'a-long-password-123']);
        $user->is_admin = true;
        $user->save();
        Sanctum::actingAs($user, ['admin']);

        $keep = $this->make();
        $a = $this->make();
        $b = $this->make();

        $this->deleteJson("/api/admin/reports/{$b->id}")->assertNoContent();
        $this->deleteJson("/api/admin/reports/{$a->id}")->assertNoContent();

        $this->assertSame($a->id, $this->make()->id);
        $this->assertSame($a->id + 1, $this->make()->id);
        $this->assertSame(1, Report::where('id', $keep->id)->count());
    }
}

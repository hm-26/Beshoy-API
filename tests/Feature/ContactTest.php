<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'Hello, I would like to work with you.',
        ], $override);
    }

    private function admin(): User
    {
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'a-long-password-123']);
        $user->is_admin = true;
        $user->save();
        Sanctum::actingAs($user, ['admin']);

        return $user;
    }

    public function test_visitor_can_send_a_message(): void
    {
        $this->postJson('/api/contact', $this->payload())->assertCreated();

        $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.com', 'read_at' => null]);
    }

    public function test_message_is_validated(): void
    {
        $this->postJson('/api/contact', $this->payload(['message' => '', 'email' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'email']);
    }

    public function test_filled_honeypot_is_silently_dropped(): void
    {
        $this->postJson('/api/contact', $this->payload(['hp' => 'http://spam.example']))->assertCreated();

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_inbox_is_admin_only(): void
    {
        $this->getJson('/api/admin/messages')->assertUnauthorized();
    }

    public function test_admin_can_list_mark_read_and_delete(): void
    {
        $message = ContactMessage::create($this->payload());
        $this->admin();

        $this->getJson('/api/admin/messages')->assertOk()->assertJsonPath('meta.unread', 1);

        $this->patchJson("/api/admin/messages/{$message->id}", ['read' => true])
            ->assertOk()->assertJsonPath('data.read', true);
        $this->getJson('/api/admin/messages/unread-count')->assertJsonPath('data.unread', 0);

        $this->deleteJson("/api/admin/messages/{$message->id}")->assertNoContent();
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_admin_publishes_contact_details_and_visitors_see_them(): void
    {
        $this->admin();
        $profile = Profile::current();

        $this->putJson('/api/admin/profile', [
            'name' => $profile->name,
            'contact' => ['email' => 'me@example.com', 'whatsapp' => '+201000000000', 'github' => 'https://github.com/me'],
        ])->assertOk();

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.contact.email', 'me@example.com')
            ->assertJsonPath('data.contact.whatsapp', '+201000000000');
    }

    public function test_javascript_links_are_rejected(): void
    {
        $this->admin();

        $this->putJson('/api/admin/profile', [
            'name' => 'X',
            'contact' => ['github' => 'javascript:alert(1)'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['contact.github']);
    }
}

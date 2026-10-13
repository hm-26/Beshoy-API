<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.require_sealed' => false]); // these tests post plain JSON; the sealed request body has its own test
    }

    public function test_email_that_could_inject_mailto_headers_is_rejected(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Visitor',
            'email' => '"x?bcc=evil@example.com"@example.com',
            'message' => 'Hello, I would like to work with you.',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}

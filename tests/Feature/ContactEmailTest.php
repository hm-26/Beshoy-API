<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_that_could_inject_mailto_headers_is_rejected(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Visitor',
            'email' => '"x?bcc=evil@example.com"@example.com',
            'message' => 'Hello, I would like to work with you.',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}

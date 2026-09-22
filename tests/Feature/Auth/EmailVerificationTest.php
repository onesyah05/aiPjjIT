<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_is_disabled_for_discord_auth(): void
    {
        $this->get('/verify-email')->assertNotFound();
    }

    public function test_email_verification_callback_is_disabled(): void
    {
        $this->get('/verify-email/1/hash')->assertNotFound();
    }

    public function test_email_verification_notification_is_disabled(): void
    {
        $this->post('/email/verification-notification')->assertNotFound();
    }
}

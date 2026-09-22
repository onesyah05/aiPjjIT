<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_is_disabled(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertNotFound();
    }

    public function test_reset_password_link_request_is_disabled(): void
    {
        $this->post('/forgot-password', ['email' => 'student@example.com'])->assertNotFound();
    }

    public function test_reset_password_screen_is_disabled(): void
    {
        $this->get('/reset-password/token')->assertNotFound();
    }

    public function test_password_reset_endpoint_is_disabled(): void
    {
        $this->post('/reset-password', [
            'token' => 'token',
            'email' => 'student@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}

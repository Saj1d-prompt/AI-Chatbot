<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Make test requests behave like requests
     * coming from our React SPA.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        /*
         * Five failed attempts are allowed.
         */
        for (
            $attempt = 1;
            $attempt <= 5;
            $attempt++
        ) {
            $response = $this->postJson(
                '/api/auth/login',
                [
                    'email' =>
                        'login@example.com',

                    'password' =>
                        'wrong-password',
                ]
            );

            $response->assertStatus(422);
        }

        /*
         * The sixth attempt should be blocked
         * by throttle:login.
         */
        $response = $this->postJson(
            '/api/auth/login',
            [
                'email' =>
                    'login@example.com',

                'password' =>
                    'wrong-password',
            ]
        );

        $response->assertStatus(429);
    }

    public function test_registration_is_rate_limited(): void
    {
        /*
         * Three registrations are allowed
         * from the same IP per minute.
         */
        for (
            $attempt = 1;
            $attempt <= 3;
            $attempt++
        ) {
            $response = $this->postJson(
                '/api/auth/register',
                [
                    'name' =>
                        "Test User {$attempt}",

                    'email' =>
                        "user{$attempt}@example.com",

                    'password' =>
                        'password123',

                    'password_confirmation' =>
                        'password123',
                ]
            );

            $response
                ->assertCreated()
                ->assertJsonPath(
                    'success',
                    true
                );
        }

        /*
         * The fourth registration from the
         * same IP should be blocked before
         * AuthController is executed.
         */
        $response = $this->postJson(
            '/api/auth/register',
            [
                'name' =>
                    'Blocked User',

                'email' =>
                    'blocked@example.com',

                'password' =>
                    'password123',

                'password_confirmation' =>
                    'password123',
            ]
        );

        $response->assertStatus(429);

        /*
         * The blocked registration must not
         * create another database user.
         */
        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'blocked@example.com',
            ]
        );
    }
}
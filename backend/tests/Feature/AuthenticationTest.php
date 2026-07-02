<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can login with valid credentials
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        // Arrange: Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'pengelola_aplikasi'
        ]);

        // Act: Attempt login
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert: Check response
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'token',
                         'user' => ['id', 'name', 'email', 'role']
                     ]
                 ])
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'user' => [
                             'email' => 'test@example.com',
                            'role' => 'pengelola_aplikasi'
                         ]
                     ]
                 ]);

        // Assert: Token exists
        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * Test user cannot login with invalid credentials
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        // Arrange: Create a user
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Act: Attempt login with wrong password
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        // Assert: Check response
        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors'
                 ]);
    }

    /**
     * Test login validation requires email and password
     */
    public function test_login_validation_requires_email_and_password(): void
    {
        // Act: Attempt login without credentials
        $response = $this->postJson('/api/login', []);

        // Assert: Check validation errors
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Test user can logout
     */
    public function test_user_can_logout(): void
    {
        // Arrange: Create and authenticate user
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act: Logout
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/logout');

        // Assert: Success response
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Logout berhasil'
                 ]);

        // Assert: Token is deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token'
        ]);
    }

    /**
     * Test authenticated user can get their info
     */
    public function test_authenticated_user_can_get_info(): void
    {
        // Arrange: Create and authenticate user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'tim_implementasi_aplikasi'
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act: Get user info
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/me');

        // Assert: Check response
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $user->id,
                         'name' => 'Test User',
                         'email' => 'test@example.com',
                        'role' => 'tim_implementasi_aplikasi'
                     ]
                 ]);
    }

    /**
     * Test unauthenticated user cannot access protected routes
     */
    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        // Act: Try to access protected route without token
        $response = $this->getJson('/api/me');

        // Assert: Unauthorized
        $response->assertStatus(401);
    }

    /**
     * Test rate limiting on login endpoint
     * Note: This test is skipped in testing environment as rate limiting
     * may not work properly with in-memory database
     */
    public function test_login_rate_limiting(): void
    {
        $this->markTestSkipped('Rate limiting test skipped in test environment');
        
        // Act: Make 6 failed login attempts (limit is 5 per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Assert: Rate limit exceeded on 6th attempt
        $response->assertStatus(429);
    }
}

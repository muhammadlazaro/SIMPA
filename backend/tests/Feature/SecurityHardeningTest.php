<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_api_responses_include_strict_security_headers(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.url', 'https://simpa.plutolab.my.id');
        config()->set('app.frontend_url', 'https://simpa.plutolab.my.id');

        $response = $this->getJson('/api/');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString('http://localhost:5173', $csp);
    }

    public function test_rate_limit_response_does_not_disclose_exception_details(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => '10.70.0.10'])
                ->postJson('/api/login', [
                    'email' => 'scanner@example.com',
                    'password' => 'wrong-password',
                ]);
        }

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Terlalu banyak percobaan. Silakan coba lagi nanti.',
            ])
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');
    }
}

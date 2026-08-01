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
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("img-src 'self' data: blob:", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("img-src 'self' data: https:", $csp);
        $this->assertStringNotContainsString('http://localhost:5173', $csp);

        // TLS is terminated by Nginx/Cloudflare; Laravel must not add a duplicate HSTS header.
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_rate_limit_response_does_not_disclose_exception_details(): void
    {
        $testIp = implode('.', [10, 70, 0, 10]);

        for ($i = 0; $i < 6; $i++) {
            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => $testIp])
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

    public function test_wrong_http_method_returns_405_without_exception_details(): void
    {
        $response = $this->getJson('/api/login');

        $response->assertStatus(405)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJson([
                'success' => false,
                'message' => 'Metode HTTP tidak diizinkan untuk endpoint ini',
            ])
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');
    }

    public function test_deployment_templates_enforce_transport_and_header_hardening(): void
    {
        $deployScript = file_get_contents(base_path('../deploy.sh'));

        $this->assertIsString($deployScript);
        $this->assertStringContainsString('server_tokens off;', $deployScript);
        $this->assertStringContainsString('if (\$http_x_forwarded_proto != "https")', $deployScript);
        $this->assertStringContainsString('return 301 https://\$host\$request_uri;', $deployScript);
        $this->assertSame(2, substr_count($deployScript, 'add_header Strict-Transport-Security'));
        $this->assertSame(2, substr_count($deployScript, 'fastcgi_hide_header Content-Security-Policy'));
        $this->assertStringContainsString("style-src 'self';", $deployScript);
        $this->assertStringContainsString("img-src 'self' data: blob:;", $deployScript);
        $this->assertStringNotContainsString("style-src 'self' 'unsafe-inline'", $deployScript);

        $robotsPath = base_path('../frontend/public/robots.txt');
        $this->assertFileExists($robotsPath);
        $this->assertStringContainsString('Disallow: /api/', (string) file_get_contents($robotsPath));
    }
}

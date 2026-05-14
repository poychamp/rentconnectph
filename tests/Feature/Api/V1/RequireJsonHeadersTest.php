<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequireJsonHeadersTest extends TestCase
{
    use RefreshDatabase;

    // ==================================================================
    // === Accept header ================================================
    // ==================================================================

    public function test_it_rejects_request_missing_accept_header(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response->assertStatus(400);
        $response->assertExactJson(['message' => 'Unauthorized.']);
    }

    public function test_it_rejects_request_with_non_json_accept_header(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    public function test_it_rejects_request_with_wildcard_accept_header(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'HTTP_ACCEPT' => '*/*',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    public function test_it_accepts_request_with_application_json_accept_header(): void
    {
        $response = $this->getJson('/api/v1/home');

        $response->assertOk();
    }

    public function test_it_accepts_request_with_charset_parameter_on_accept_header(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'HTTP_ACCEPT' => 'application/json; charset=utf-8',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response->assertOk();
    }

    // ==================================================================
    // === Content-Type header ==========================================
    // ==================================================================

    public function test_it_rejects_request_missing_content_type_header(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    public function test_it_rejects_request_with_non_json_content_type_header(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'text/plain',
        ]);

        $response->assertStatus(400);
    }

    public function test_it_accepts_request_with_charset_parameter_on_content_type_header(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json; charset=utf-8',
        ]);

        $response->assertOk();
    }

    // ==================================================================
    // === Response shape ===============================================
    // ==================================================================

    public function test_400_response_is_json_even_when_accept_header_is_not_json(): void
    {
        $response = $this->call('GET', '/api/v1/home', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response->assertStatus(400);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertExactJson(['message' => 'Unauthorized.']);
    }
}

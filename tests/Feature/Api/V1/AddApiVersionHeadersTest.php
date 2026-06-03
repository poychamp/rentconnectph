<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddApiVersionHeadersTest extends TestCase
{
    use RefreshDatabase;


    // =========================================================================
    // Header presence — one case per header so a single drift surfaces narrowly
    // =========================================================================

    public function test_it_attaches_min_android_consumer_version_header_from_config(): void
    {
        config(['api.min_android_consumer_version' => '2.5.1']);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertHeader('X-Min-Android-Consumer-Version', '2.5.1');
    }

    public function test_it_attaches_min_android_field_version_header_from_config(): void
    {
        config(['api.min_android_field_version' => '1.4.0']);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertHeader('X-Min-Android-Field-Version', '1.4.0');
    }

    public function test_it_attaches_min_ios_version_header_from_config(): void
    {
        config(['api.min_ios_version' => '3.0.0']);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertHeader('X-Min-iOS-Version', '3.0.0');
    }

    // =========================================================================
    // Error-response coverage — headers must survive other middleware
    // short-circuiting (e.g. RequireJsonHeaders returning 400 without calling
    // $next). Pins that AddApiVersionHeaders is registered OUTER of the
    // RequireJsonHeaders middleware in the api group.
    // =========================================================================

    public function test_it_attaches_headers_on_error_responses(): void
    {
        config([
            'api.min_android_consumer_version' => '2.5.1',
            'api.min_android_field_version'    => '1.4.0',
            'api.min_ios_version'              => '3.0.0',
        ]);

        // Bare GET (no Accept: application/json) → RequireJsonHeaders 400.
        // Version headers should still be attached.
        $this->call('GET', route('api.v1.home'))
            ->assertStatus(400)
            ->assertHeader('X-Min-Android-Consumer-Version', '2.5.1')
            ->assertHeader('X-Min-Android-Field-Version', '1.4.0')
            ->assertHeader('X-Min-iOS-Version', '3.0.0');
    }
}

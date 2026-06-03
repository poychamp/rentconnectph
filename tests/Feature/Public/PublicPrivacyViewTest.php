<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

class PublicPrivacyViewTest extends TestCase
{
    public function test_it_renders_the_privacy_page(): void
    {
        $this->get('/privacy')->assertOk();
    }
}

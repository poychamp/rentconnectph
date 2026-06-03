<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

class PublicTermsViewTest extends TestCase
{
    public function test_it_renders_the_terms_page(): void
    {
        $this->get('/terms')->assertOk();
    }
}

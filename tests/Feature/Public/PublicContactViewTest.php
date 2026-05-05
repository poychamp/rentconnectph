<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

class PublicContactViewTest extends TestCase
{
    public function test_it_renders_the_contact_form(): void
    {
        $this->get('/contact')->assertOk();
    }

    public function test_it_renders_the_contact_sent_page(): void
    {
        $this->get('/contact-sent')->assertOk();
    }
}

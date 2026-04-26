<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        $uses = parent::setUpTraits();

        if (isset($uses[RefreshDatabase::class]) && isset($uses[SeedDatabaseAfterRefresh::class])) {
            $this->seedDatabaseAfterRefresh();
        }

        return $uses;
    }
}

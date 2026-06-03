<?php

namespace Tests;

use Database\Seeders\TestSeeder;

trait SeedDatabaseAfterRefresh
{
    public function seedDatabaseAfterRefresh()
    {
        $this->seed(TestSeeder::class);
    }
}

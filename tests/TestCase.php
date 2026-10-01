<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\ForbidDestructiveDatabaseReset;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication()
    {
        parent::refreshApplication();

        ForbidDestructiveDatabaseReset::install($this->app);
    }

    public function artisan($command, $parameters = [])
    {
        ForbidDestructiveDatabaseReset::assertCommandAllowed((string) $command);

        return parent::artisan($command, $parameters);
    }
}

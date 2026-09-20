<?php

namespace Tests;

use App\Support\Access;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The role ladder is read once and kept for the request. A test rolls
        // its database back but not that, so each test starts with it empty.
        Access::flush();
    }
}

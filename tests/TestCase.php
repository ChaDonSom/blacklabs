<?php

namespace Tests;

use App\Services\RunsProcesses;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RunsProcesses;
}

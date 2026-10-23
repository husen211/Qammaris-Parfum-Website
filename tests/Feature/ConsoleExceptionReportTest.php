<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\TestCase;

/** OPS-DEPLOY-01: reporting an error without an HTTP request (artisan) must not throw a second, misleading error. */
class ConsoleExceptionReportTest extends TestCase
{
    public function test_reporting_without_a_request_keeps_the_original_error_visible(): void
    {
        $this->app->forgetInstance('request');
        $this->app->offsetUnset('request');
        $this->assertFalse($this->app->bound('request'));

        report(new RuntimeException('Original boot error'));

        $this->assertTrue(true, 'report() returned without "Class "request" does not exist"');
    }
}

<?php

namespace Artryazanov\ErrorMailer\Tests;

use Artryazanov\ErrorMailer\ErrorMailerServiceProvider;
use Artryazanov\ErrorMailer\Facades\ErrorMailer;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ErrorMailerServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'ErrorMailer' => ErrorMailer::class,
        ];
    }
}

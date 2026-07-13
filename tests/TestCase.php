<?php

namespace Artryazanov\ErrorMailer\Tests;

use Artryazanov\ErrorMailer\ErrorMailerServiceProvider;
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
     * @param  \Illuminate\Foundation\Application  $app
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
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'ErrorMailer' => \Artryazanov\ErrorMailer\Facades\ErrorMailer::class,
        ];
    }
}

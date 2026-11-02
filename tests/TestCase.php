<?php

declare(strict_types=1);

namespace Artryazanov\ErrorMailer\Tests;

use Artryazanov\ErrorMailer\ErrorMailerServiceProvider;
use Artryazanov\ErrorMailer\Facades\ErrorMailer;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * The latest response.
     *
     * @var mixed
     */
    public static $latestResponse;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ErrorMailerServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'ErrorMailer' => ErrorMailer::class,
        ];
    }
}
